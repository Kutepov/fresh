<?php namespace common\services;

use Assert\Assertion;
use common\components\queue\jobs\ArticlePostingJob;
use common\components\queue\jobs\ArticlePushNotificationJob;
use common\components\queue\jobs\SourceUrlFirstTimeParsingJob;
use common\components\queue\jobs\TopArticlePushNotificationJob;
use common\components\queue\jobs\InitAppSourcesSubscriptions;
use common\components\queue\jobs\SubscriptionStatusProcessingJob;
use common\components\queue\jobs\TelegramApproveRequestsJob;
use common\components\queue\jobs\TopArticlesStatisticJob;
use common\components\queue\jobs\TopQueriesCacheJob;
use common\components\scrapers\dto\ArticleItem;
use common\models\App;
use common\models\Article;
use common\models\SourceUrl;
use common\components\queue\jobs\ArticlesProcessingJob;
use yii\base\BaseObject;
use yii;

class QueueManager extends BaseObject
{
    private $multilingualService;

    public function __construct($config = [])
    {
        $this->multilingualService = Yii::$container->get(MultilingualService::class);
        parent::__construct($config);
    }

    /**
     * Создание задачи для обработки спарсенных кратких новостей
     * @param SourceUrl $sourceUrl
     * @param array $articlesItems
     * @param bool $userSource - запускать в отдельной "юзерской" очереди
     * @param bool $debug
     * @return mixed
     */
    public function createArticlesProcessingJob(SourceUrl $sourceUrl, array $articlesItems = [], bool $userSource = false, bool $debug = false)
    {
        Assertion::allIsInstanceOf($articlesItems, ArticleItem::class);
        Assertion::minCount($articlesItems, 1);

        if (!$userSource && $this->multilingualService->isCISCountryCode($sourceUrl->source->country)) {
            $priority = 1;
        } else {
            $priority = 10;
        }

        return Yii::$app->{$userSource ? 'usersSourcesQueue' : 'queue'}->priority($priority)->push(new ArticlesProcessingJob([
            'sourceUrlId' => $sourceUrl->id,
            'items' => $articlesItems,
            'debug' => $debug
        ]));
    }

    public function createTopArticlesStatisticsJob()
    {
        return Yii::$app->queue->push(new TopArticlesStatisticJob());
    }

    public function createTelegramApproveRequestsJob()
    {
        return Yii::$app->queue->push(new TelegramApproveRequestsJob());
    }

    public function createTopQueriesCacheJob()
    {
        return Yii::$app->queue->push(new TopQueriesCacheJob());
    }

    public function createArticlePostingJob(Article $article)
    {
        return Yii::$app->queue->priority(1)->push(new ArticlePostingJob([
            'articleId' => $article->id
        ]));
    }

    public function createTopArticlePushNotificationJob(Article $article)
    {
        return Yii::$app->queue->priority(1)->push(new TopArticlePushNotificationJob([
            'articleId' => $article->id
        ]));
    }

    public function createArticlePushNotificationJob(Article $article)
    {
        return Yii::$app->queue->priority(5)->push(new ArticlePushNotificationJob([
            'articleId' => $article->id
        ]));
    }

    public function createSubscriptionStatusProcessingJob(string $userId, ?string $platform = null)
    {
        return Yii::$app->subscriptionsQueue->push(new SubscriptionStatusProcessingJob([
            'uid' => $userId,
            'platform' => $platform
        ]));
    }

    public function createAppSourcesSubscriptionInitialRecalc(App $app, array $enabledSourcesUrls = [])
    {
        return Yii::$app->countersQueue->push(new InitAppSourcesSubscriptions([
            'appId' => $app->id,
            'enabledSourcesUrls' => $enabledSourcesUrls
        ]));
    }

    public function createSourceUrlFirstTimeParsingJob(SourceUrl $sourceUrl)
    {
        return Yii::$app->usersSourcesQueue->push(new SourceUrlFirstTimeParsingJob([
            'sourceUrlId' => $sourceUrl->id
        ]));
    }
}