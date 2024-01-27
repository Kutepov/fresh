<?php namespace api\controllers;

use common\components\helpers\Api;
use common\models\App;
use common\services\IAP\GoogleValidator;
use common\services\QueueManager;
use yii\base\UserException;
use yii\web\BadRequestHttpException;
use yii\web\Request;
use yii\helpers\ArrayHelper;
use yii\filters\VerbFilter;

class AppsController extends Controller
{
    private QueueManager $queueManager;

    private $currentVersions = [
        App::PLATFORM_ANDROID => '1',
        App::PLATFORM_IOS => '1'
    ];

    public function __construct($id, $module, QueueManager $queueManager, $config = [])
    {
        $this->queueManager = $queueManager;
        parent::__construct($id, $module, $config);
    }

    public function behaviors(): array
    {
        return ArrayHelper::merge(parent::behaviors(), [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'enable-push-notifications' => ['post'],
                    'disable-push-notifications' => ['post']
                ],
            ],
        ]);
    }

    /**
     * Проверка необходимости обновить приложение
     * @param Request
     * @return bool|int
     * @throws BadRequestHttpException
     */
    public function actionCheckForceUpdateNeeded(Request $request)
    {
        if (!$request->get('version')) {
            throw new BadRequestHttpException();
        }

        return version_compare($this->currentVersions[$request->get('platform')], $request->get('version'), '>=');
    }

    public function actionValidateIosSubscription(Request $request)
    {
        if ($userId = $request->post('userId')) {
            $this->queueManager->createSubscriptionStatusProcessingJob(
                $userId,
                $this->currentApp->platform
            );
        }
    }

    public function actionValidateAndroidSubscription(Request $request, GoogleValidator $validator)
    {
        $isValid = $validator->validateSubscription(
            $request->getBodyParam('token'),
            $request->getBodyParam('subscriptionId')
        );

        return [
            'isValid' => Api::version(Api::V_2_0) ? $isValid : (int)$isValid
        ];
    }

    /**
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionEnablePushNotifications(Request $request): bool
    {
        try {
            $this->currentApp->enablePushNotifications(
                $request->post('token', ''),
                $request->post('sourceUrl', [])
            );
        } catch (\Throwable $e) {
            throw new BadRequestHttpException($e->getMessage());
        }

        return true;
    }

    /**
     * @throws BadRequestHttpException
     */
    public function actionDisablePushNotifications(Request $request)
    {

        try {
            $this->currentApp->disablePushNotifications(
                $request->post('sourceUrl', [])
            );
        } catch (\Throwable $e) {
            throw new BadRequestHttpException($e->getMessage());
        }

        return true;
    }
}