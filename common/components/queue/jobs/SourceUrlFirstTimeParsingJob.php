<?php namespace common\components\queue\jobs;

use common\components\scrapers\dto\ArticleItem;
use common\models\SourceUrl;
use common\services\QueueManager;

class SourceUrlFirstTimeParsingJob extends Job
{
    public $sourceUrlId;

    private QueueManager $queueManager;

    public function __construct($config = [])
    {
        $this->queueManager = \Yii::$container->get(QueueManager::class);
        parent::__construct($config);
    }

    public function execute($queue)
    {
        if (
            ($sourceUrl = SourceUrl::findOne($this->sourceUrlId)) &&
            (
                $sourceUrl->last_scraped_article_date_disabled ||
                (
                    !$sourceUrl->last_scraped_article_date &&
                    !$sourceUrl->last_scraped_article_date_disabled
                )
            )
        ) {
            $sourceUrl->lockForScraping();
            $sourceUrl->scraper->parseArticlesList($sourceUrl->url)->then(
                function (array $articles) use (&$sourceUrl) {
                    /** @var ArticleItem[] $articles */
                    $articles = array_filter($articles);

                    if (count($articles)) {
                        $this->queueManager->createArticlesProcessingJob(
                            $sourceUrl,
                            $articles,
                            true
                        );

                        try {
                            if (!$sourceUrl->default && !$sourceUrl->last_scraped_article_date_disabled) {
                                $timezone = (string)$articles[0]->getPublicationDate()->getTimezone()->toRegionTimeZone();
                                $sourceUrl->updateAttributes([
                                    'timezone' => $timezone
                                ]);
                                $sourceUrl->source->updateAttributes([
                                    'timezone' => $timezone
                                ]);
                            }
                        } catch (\Throwable $e) {
                        }
                    } else {
                        $sourceUrl->unlockForScraping(false);
                    }
                },
                function (\Throwable $exception) use (&$sourceUrl) {
                    $sourceUrl->unlockForScraping(true);
                })
                ->wait();
        }
    }
}