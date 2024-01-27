<?php namespace common\services\push;

use common\models\App;
use common\models\Article;
use common\models\pivot\SourceUrlSubscription;
use common\models\PushNotification;
use Pushok\Client;
use Pushok\Notification;
use Pushok\Payload;
use Pushok\Response;
use yii\helpers\ArrayHelper;

class IOSPushNotificationsService extends PushNotificationsService
{
    private $apnsClient;

    public function __construct(Client $apnsClient)
    {
        $this->apnsClient = $apnsClient;
    }

    public function pushTopArticle(Article $article): void
    {
        if (!$article->source->default || PushNotification::find()->where(['article_id' => $article->id, 'platform' => App::PLATFORM_IOS, 'top' => 1])->exists()) {
            return;
        }

        $appsQuery = App::find()
            ->withEnabledPushes()
            ->iosOnly()
            ->andWhere([
                'country' => ArrayHelper::getColumn($article->source->countries, 'code'),
                'articles_language' => $article->source->language
            ])
            ->asArray()
            ->batch(3000);

        foreach ($appsQuery as $apps) {
            $batchNotifications = [];
            foreach ($apps as $app) {

                /** Для новой версии приложения фильтруем по включенным урлам источников вместо папок и источников */
                if (version_compare($app['version'], '3.0.0', '>=')) {
                    $enabledSourcesUrls = (array)json_decode($app['enabled_sources_urls'], true);
                    if (!$enabledSourcesUrls || !in_array($article->source_url_id, $enabledSourcesUrls)) {
                        continue;
                    }
                } else {
                    $enabledCategories = (array)json_decode($app['enabled_categories'], true);
                    $enabledSources = (array)json_decode($app['enabled_sources'], true);
                    if (($enabledCategories && !in_array($article->category_name, $enabledCategories, true)) ||
                        ($enabledSources && !in_array($article->source_id, $enabledSources, true))
                    ) {
                        continue;
                    }
                }

                $notificationLog = $this->createNotificationLog($article, $app, true);
                $this->apnsClient->addNotification(
                    $this->createNotification(
                        $article,
                        $app['push_token'],
                        $notificationLog->id
                    )
                );
                $batchNotifications[] = $notificationLog->toArray();
            }

            if (count($batchNotifications)) {
                \Yii::$app->db->createCommand()->batchInsertIgnoreFromArray(
                    PushNotification::tableName(),
                    $batchNotifications
                )->execute();
            }
        }

        $this->apnsClient->push();
    }

    private function createNotification(Article $article, string $deviceToken, string $internalId): Notification
    {
        $alert = Payload\Alert::create()
            ->setBody($article->title);

        $payload = Payload::create()
            ->setMutableContent(true)
            ->setAlert($alert)
            ->setSound('default')
            ->setCustomValue('data', [
                'type' => 'article',
                'id' => $article->id,
                'image' => $article->previewImageUrl,
                'trigger-id' => $internalId
            ]);

        return new Notification($payload, $deviceToken, $internalId);
    }

    public function pushRegularArticle(Article $article): void
    {
        if (PushNotification::find()->where(['article_id' => $article->id, 'platform' => App::PLATFORM_IOS, 'top' => 0])->exists()) {
            return;
        }

        $appsQuery = App::find()
            ->iosOnly()
            ->proOnly()
            ->andWhere([
                'apps.id' => SourceUrlSubscription::find()->select('app_id')->where(['source_url_id' => $article->source_url_id, 'push' => 1])
            ])
            ->asArray()
            ->batch(3000);

        foreach ($appsQuery as $apps) {
            $batchNotifications = [];
            foreach ($apps as $app) {
                $notificationLog = $this->createNotificationLog($article, $app);
                $this->apnsClient->addNotification(
                    $this->createNotification(
                        $article,
                        $app['push_token'],
                        $notificationLog->id
                    )
                );
                $batchNotifications[] = $notificationLog->toArray();
            }

            if (count($batchNotifications)) {
                \Yii::$app->db->createCommand()->batchInsertIgnoreFromArray(
                    PushNotification::tableName(),
                    $batchNotifications
                )->execute();
            }
        }

        $this->apnsClient->push();
    }
}