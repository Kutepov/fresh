<?php namespace api\controllers\statistics;

use api\controllers\Controller;
use common\models\PushNotification;

class PushNotificationsController extends Controller
{
    public function actionViewed($id): bool
    {
        if ($log = PushNotification::findOne($id)) {
            $log->updateAttributes([
                'viewed' => 1
            ]);
        }
        return true;
    }

    public function actionClicked($id): bool
    {
        if ($log = PushNotification::findOne($id)) {
            $log->updateAttributes([
                'clicked' => 1
            ]);
        }
        return true;
    }
}