<?php namespace common\components\queue\jobs;

use common\contracts\Poster;
use common\models\Article;
use common\services\push\AndroidPushNotificationsService;
use common\services\push\IOSPushNotificationsService;

class TopArticlePushNotificationJob extends Job
{
    public $articleId;

    public function execute($queue)
    {
        $iosPusher = \Yii::$container->get(IOSPushNotificationsService::class);
        $androidPusher = \Yii::$container->get(AndroidPushNotificationsService::class);

        if ($article = Article::findOne($this->articleId)) {
            $iosPusher->pushTopArticle($article);
            $androidPusher->pushTopArticle($article);
        }
    }

    public function getTtr()
    {
        return 1800 * 6;
    }

    public function canRetry($attempt, $error)
    {
        return false;
    }
}