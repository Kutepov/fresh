<?php namespace common\services\push;

use common\models\App;
use common\models\Article;
use common\models\pivot\SourceUrlSubscription;
use common\models\PushNotification;
use GuzzleHttp\Promise\Each;
use paragraph1\phpFCM\Client;
use paragraph1\phpFCM\Message;
use paragraph1\phpFCM\Notification;
use paragraph1\phpFCM\Recipient\Device;
use yii\helpers\ArrayHelper;

class AndroidPushNotificationsService extends PushNotificationsService
{
    private $fcmClient;

    public function __construct(Client $client)
    {
        $this->fcmClient = $client;
    }

    public function pushTopArticle(Article $article): void
    {
        if (!$article->source->default || PushNotification::find()->where(['article_id' => $article->id, 'platform' => App::PLATFORM_ANDROID, 'top' => 1])->exists()) {
            return;
        }

        $appsQuery = App::find()
            ->withEnabledPushes()
            ->androidOnly()
            ->andWhere([
                'country' => ArrayHelper::getColumn($article->source->countries, 'code'),
                'articles_language' => $article->source->language
            ])
            ->asArray()
            ->batch(1000);

        foreach ($appsQuery as $apps) {
            $batchNotifications = [];
            $asyncRequests = [];
            foreach ($apps as $app) {

                /** Для новой версии приложения фильтруем по включенным урлам источников вместо папок и источников */
                if (version_compare($app['version'], '5.0.0', '>=')) {
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
                $asyncRequests[] = $this->fcmClient->sendAsync(
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

            if (count($asyncRequests)) {
                Each::ofLimit($asyncRequests, 32)->wait();
            }
            sleep(30);
        }
    }

    private function createNotification(Article $article, string $deviceToken, string $internalId): Message
    {
        $message = new Message();
        $notification = new Notification($article->title, $article->description);
        $message->setNotification($notification);
        $message->addRecipient(new Device($deviceToken));
        $message->setPriority(Message::PRIORITY_HIGH);
        $message->setData([
            'title' => $article->title,
            'type' => 'article',
            'id' => $article->id,
            'image' => $article->previewImageUrl,
            'trigger-id' => $internalId,
            'description' => $article->description
        ]);

        return $message;
    }

    public function pushRegularArticle(Article $article): void
    {
        if (PushNotification::find()->where(['article_id' => $article->id, 'platform' => App::PLATFORM_ANDROID, 'top' => 0])->exists()) {
            return;
        }


        $appsQuery = App::find()
            ->androidOnly()
            ->proOnly()
            ->andWhere([
                'apps.id' => SourceUrlSubscription::find()->select('app_id')->where(['source_url_id' => $article->source_url_id, 'push' => 1])
            ])
            ->asArray()
            ->batch(1000);

        foreach ($appsQuery as $apps) {
            $batchNotifications = [];
            $asyncRequests = [];
            foreach ($apps as $app) {
                $notificationLog = $this->createNotificationLog($article, $app);
                $asyncRequests[] = $this->fcmClient->sendAsync(
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

            if (count($asyncRequests)) {
                Each::ofLimit($asyncRequests, 32)->wait();
            }
            sleep(30);
        }
    }
}