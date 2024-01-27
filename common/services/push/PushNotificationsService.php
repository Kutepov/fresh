<?php namespace common\services\push;

use common\models\Article;
use common\models\PushNotification;

abstract class PushNotificationsService
{
    abstract public function pushTopArticle(Article $article): void;
    abstract public function pushRegularArticle(Article $article): void;

    protected function createNotificationLog(Article $article, $app, bool $top = false): PushNotification
    {
        $notification = new PushNotification([
            'article_id' => $article->id,
            'app_id' => $app['id'],
            'country' => $article->source->country,
            'articles_language' => $article->source->language,
            'platform' => $app['platform'],
            'top' => (int)$top
        ]);

        $notification->createUUID();

        return $notification;
    }

}