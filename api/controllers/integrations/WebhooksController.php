<?php declare(strict_types=1);

namespace api\controllers\integrations;

use common\models\App;
use common\services\apps\Adapty;
use common\services\QueueManager;
use yii\helpers\Json;
use yii\web\Controller;
use yii\web\Request;

class WebhooksController extends Controller
{
    private $adapty;
    private QueueManager $queueManager;

    public function __construct($id, $module, Adapty $adapty, QueueManager $queueManager, $config = [])
    {
        $this->adapty = $adapty;
        $this->queueManager = $queueManager;

        parent::__construct($id, $module, $config);
    }

    public function beforeAction($action)
    {
        $this->enableCsrfValidation = false;
        return parent::beforeAction($action);
    }

    public function actionAdapty(Request $request)
    {
        $event = Json::decode($request->rawBody) ?: $request->post();

        if (in_array($event['event_type'], ['subscription_expired', 'subscription_refunded'], true)) {
            $this->queueManager->createSubscriptionStatusProcessingJob(
                $event['customer_user_id'],
                $event['event_properties']['store'] === 'app_store' ? App::PLATFORM_IOS : App::PLATFORM_ANDROID
            );
            return true;
        }

        return $this->adapty->redirectEventToTelegram($event);
    }
}