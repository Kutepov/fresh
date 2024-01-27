<?php namespace console\controllers\daemons;

use Carbon\Carbon;
use common\components\scrapers\events\SourceUrlParsingCompletedEvent;
use common\components\scrapers\events\SourceUrlParsingFailedEvent;
use common\models\Source;
use common\models\SourceUrl;
use common\services\DbManager;
use common\services\Forker;
use common\services\QueueManager;
use console\controllers\Controller;
use GuzzleHttp\Promise\Each;
use yii\console\ExitCode;
use yii;
use yii\helpers\Console;

class ArticlesScraperController extends Controller
{
    private const SCRAPING_INTERVAL_IN_SECONDS = 60;
    private const SCRAPING_INTERVAL_IN_SECONDS_CIS = 60;
    private const RESTART_DAEMON_AFTER_MINUTES = 10;
    private const MAX_REQUESTS_CONCURRENCY = 12;
    private const MAX_REQUESTS_CONCURRENCY_CIS = 8;

    public $daemonize = true;
    public $sourceId;
    public $sourceUrlId;
    public $maxProcesses = 16;

    public $defaultOnly = true;

    /** @var Forker */
    private $forker;

    /** @var QueueManager */
    private $queueManager;

    /** @var DbManager */
    private $dbManager;

    public function options($actionID)
    {
        return yii\helpers\ArrayHelper::merge(parent::options($actionID), [
            'daemonize',
            'sourceId',
            'sourceUrlId',
            'maxProcesses',
            'defaultOnly'
        ]);
    }

    public function __construct($id, $module, Forker $forker, QueueManager $queueManager, DbManager $dbManager, $config = [])
    {
        $this->forker = $forker;
        $this->queueManager = $queueManager;
        $this->dbManager = $dbManager;

        parent::__construct($id, $module, $config);
    }

    public function actionIndex($country = null, $language = null): int
    {
        $startTime = Carbon::now();

        while ($startTime->diffInMinutes() < self::RESTART_DAEMON_AFTER_MINUTES) {
            if ($sources = $this->getSourcesWithUnlockedUrls($country, $language)) {
                $this->stdOutDebug('Sources: ' . count($sources), Console::FG_YELLOW);
                foreach ($sources as $source) {
                    $this->lockUrlsForScraping($source);

                    $this->forker->invoke(function () use ($source) {
                        $this->createEventListener();
                        $this->sendAsyncRequests($source);
                    }, $this->maxProcesses);
                }
            } else {
                $this->stdOutDebug('Skipping', Console::FG_YELLOW);
            }

            if (!$this->daemonize) {
                break;
            }

            sleep(1);
        }

        $this->forker->wait();
        return ExitCode::OK;
    }

    private function sendAsyncRequests(Source $source): void
    {
        $promises = function () use ($source) {
            foreach ($source->urls as $sourceUrl) {
                if ($scraper = $sourceUrl->getScraper($this->debug)) {
                    yield $scraper
                        ->parseArticlesList($sourceUrl->url)
                        ->then(function (array $response) use ($sourceUrl) {
                            $this->trigger(SourceUrlParsingCompletedEvent::NAME, new SourceUrlParsingCompletedEvent(
                                $response,
                                $sourceUrl
                            ));
                        }, function (\Throwable $exception) use ($sourceUrl) {
                            $this->trigger(SourceUrlParsingFailedEvent::NAME, new SourceUrlParsingFailedEvent(
                                $sourceUrl,
                                $exception
                            ));
                        });
                }
            }
        };

        Each::ofLimit($promises(),
            $this->getMaxRequestsConcurrency($source->country)
        )->otherwise(function ($e) use ($source) {
            Yii::error([
                'sourceId' => $source->id,
                'urls' => array_map(static function (SourceUrl $url) {
                    return $url->url;
                }, $source->urls),
            ]);
            Yii::error($e);
        })->wait();
    }

    private function lockUrlsForScraping(Source $source): void
    {
        if ($this->daemonize) {
            array_map(static function (SourceUrl $sourceUrl) {
                $sourceUrl->lockForScraping();
            }, $source->urls);
        }
    }

    private function createEventListener(): void
    {
        $results = [];

        $this->on('event.sources.parsing.*', function ($event) use (&$results) {
            if ($event instanceof SourceUrlParsingCompletedEvent) {
                $this->stdOutDebug(
                    'URL успешно спарсен: ' . $event->sourceUrl->url
                    . ' Найдено новостей: ' . count($event->articlesItems),
                    Console::FG_GREEN
                );

                if ($this->daemonize && !count($event->articlesItems)) {
                    $event->sourceUrl->unlockForScraping(false, count($event->articlesItems));
                }
            } elseif ($event instanceof SourceUrlParsingFailedEvent) {
                $results[$event->sourceUrl->source_id][] = false;

                $this->stdErrDebug('Ошибка парсинга URL: ' . $event->sourceUrl->url
                    . ' ' . $event->exception->getMessage()
                    . ' ' . $event->exception->getFile()
                    . ':' . $event->exception->getLine());

                if ($this->daemonize) {
                    $event->sourceUrl->unlockForScraping(true);
                }
            }

            $articlesItems = array_filter($event->articlesItems);
            if (count($articlesItems) > 0) {
                $this->queueManager->createArticlesProcessingJob(
                    $event->sourceUrl,
                    $articlesItems,
                    false,
                    $this->debug
                );
            }

            $this->stdOutDebug('Парсинг источника "' . $event->sourceUrl->source->name . '" окончен. Найдено свежих новостей: ' . count($articlesItems), yii\helpers\Console::FG_GREEN);
        });
    }

    /**
     * @param $forCountry
     * @param $forLanguage
     * @return Source[]
     */
    private function getSourcesWithUnlockedUrls($forCountry, $forLanguage = null): array
    {
        return $this->dbManager->wrap(function () use ($forCountry, $forLanguage) {
            $query = Source::find()
                ->enabled()
                ->defaultOnly($this->defaultOnly)
                ->innerJoinWith([
                    'urls' => function (\common\queries\SourceUrl $query) use ($forCountry) {
                        $query->andWhere(['sources_urls.enabled' => 1]);
                        if ($this->daemonize) {
                            $query->notLocked()
                                ->defaultOnly($this->defaultOnly);

                            if (!$this->defaultOnly) {
                                $query->withSubscribersOnly();
                            }

                            $query->andWhere([
                                'OR',
                                ['IS', 'last_scraped_at', null],
                                ['<=', 'last_scraped_at', Carbon::parse(
                                    sprintf('%s seconds ago', $this->getScrapingInterval($forCountry))
                                )]
                            ]);

                            $query->andWhere([
                                'OR',
                                ['IS', 'sources_urls.last_scraped_article_date', null],
                                [
                                    '<=',
                                    'sources_urls.last_scraped_article_date',
                                    $this->getLastArticleTimeRestriction($forCountry)
                                ]
                            ]);
                        }
                        $query->orderBy(['sources_urls.last_scraped_article_date' => SORT_ASC]);
                        $query->andFilterWhere([
                            'sources_urls.id' => $this->sourceUrlId
                        ]);
                    }
                ], true)
                ->byCountry($forCountry)
                ->byLanguage($forLanguage)
                ->andFilterWhere([
                    'sources.id' => $this->sourceId
                ]);

                if (!$this->defaultOnly) {
                    return $query->withSubscribersOnly()->all();
                }

                return $query->all();
        });
    }

    private function isCis($country): bool
    {
        return in_array(
            strtolower($country),
            ['ru', 'ua', 'by', 'kz']
        );
    }

    private function getScrapingInterval($country): int
    {
        if (!$country) {
            return 300;
        }

        if ($this->isCis($country)) {
            return self::SCRAPING_INTERVAL_IN_SECONDS_CIS;
        }

        return self::SCRAPING_INTERVAL_IN_SECONDS;
    }

    private function getMaxRequestsConcurrency($country): int
    {
        if (!$country) {
            return 8;
        }

        if ($this->isCis($country)) {
            return self::MAX_REQUESTS_CONCURRENCY_CIS;
        }

        return self::MAX_REQUESTS_CONCURRENCY;
    }

    private function getLastArticleTimeRestriction($country): Carbon
    {
        if (!$country) {
            return Carbon::parse('5 minutes ago');
        }

        if ($this->isCis($country)) {
            Carbon::parse('20 seconds ago');
        }

        return Carbon::parse('2 minutes ago');
    }
}