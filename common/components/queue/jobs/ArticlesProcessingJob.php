<?php namespace common\components\queue\jobs;

use Carbon\Carbon;
use common\components\scrapers\common\ArticleBodyScraper;
use common\components\scrapers\common\exceptions\ParsingResultException;
use common\components\scrapers\dto\ArticleBody;
use common\components\scrapers\dto\ArticleBodyWithDescription;
use common\components\scrapers\dto\ArticleItem;
use common\components\scrapers\services\ParsingResultValidator;
use common\contracts\Logger;
use common\models\Article;
use common\models\Source;
use common\models\SourceUrl;
use GuzzleHttp\Promise\Each;

class ArticlesProcessingJob extends Job
{
    public $sourceUrlId;
    /** @var ArticleItem[] */
    public $items = [];

    private $scraper;
    /** @var SourceUrl */
    private $sourceUrl;
    /** @var Logger */
    private $logger;
    /** @var ParsingResultValidator */
    private $validator;

    public function __construct($config = [])
    {
        $this->logger = \Yii::$container->get(Logger::class);
        $this->validator = \Yii::$container->get(ParsingResultValidator::class);
        parent::__construct($config);
    }

    public function execute($queue)
    {
        $this->sourceUrl = SourceUrl::findOne($this->sourceUrlId);

        $this->scraper = $this->sourceUrl->getScraper($this->debug);

        if ($this->scraper instanceof ArticleBodyScraper && (in_array($this->sourceUrl->source->type, [Source::TYPE_PREVIEW, Source::TYPE_FULL_ARTICLE], true))) {
            $requests = [];

            foreach ($this->items as $k => $item) {
                if (is_null($item->getBody())) {
                    $requests[$k] = $this->scraper->parseArticleBody($item->getUrl());
                }
            }

            $promise = Each::ofLimit($requests, 16, function (ArticleBody $body, $key) use (&$requests) {
                try {
                    if ($this->validator->validateArticleBody($body)) {
                        $this->items[$key]->setBody($body);

                        if ($body instanceof ArticleBodyWithDescription) {
                            $this->items[$key]->setDescription($body->getDescription());
                        }
                    }
                    else {
                        $this->logger->scraperPossibleWrongArticleBody($this->scraper, $body, $this->items[$key]->getUrl());
                    }
                } catch (ParsingResultException $e) {
                    $this->logger->scraperEmptyArticleBody($this->scraper, $this->items[$key]->getUrl());
                    unset($this->items[$key]);
                }
            }, function (\Throwable $exception, $key) {
                $this->logger->scraperArticleBodyException($exception, $this->scraper, $this->items[$key]->getUrl());
                unset($this->items[$key]);
            });

            try {
                $promise->otherwise(function ($e) {
                    \Yii::error($e);
                })->wait();
            } catch (\Throwable $e) {
                \Yii::error([
                    'sourceUrlId' => $this->sourceUrlId,
                    'source' => $this->sourceUrl->source->url,
                ]);
                throw $e;
            }
        }

        foreach ($this->items as $item) {
            try {
                Article::createFromDto($item, $this->sourceUrl);
            } catch (\Throwable $e) {
                //TODO
                \Yii::error([
                    'sourceUrlId' => $this->sourceUrlId,
                    'source' => $this->sourceUrl->source->url,
                    'url' => $item->getUrl(),
                    'body' => $item->getBody(),
                    'description' => $item->getDescription(),
                ]);
                \Yii::error($e);
            }
        }

        $this->sourceUrl->unlockForScraping(false, count($this->items));

        if ($maxPublicationDate = $this->getMaxPublicationDate()) {
            $this->sourceUrl->updateLastScrapedArticleDate($maxPublicationDate);
        }
    }

    private function getMaxPublicationDate(): ?Carbon
    {
        if ($this->sourceUrl->last_scraped_article_date_disabled) {
            return null;
        }

        if (count($this->items)) {
            usort($this->items, static function (ArticleItem $a, ArticleItem $b) {
                if ($a->getPublicationDate() === $b->getPublicationDate()) {
                    return 0;
                }

                return $a->getPublicationDate() > $b->getPublicationDate() ? -1 : 1;
            });

            foreach ($this->items as $item) {
                $maxPublicationDate = $item->getPublicationDate()->setTimezone('UTC');
                if ($maxPublicationDate > $this->sourceUrl->last_scraped_article_date && $maxPublicationDate <= Carbon::now()) {
                    return $maxPublicationDate;
                }
            }
        }

        return $this->sourceUrl->last_scraped_article_date;
    }
}