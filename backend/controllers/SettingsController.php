<?php namespace backend\controllers;

use backend\models\forms\SettingsForm;
use backend\models\forms\SettingsPushNotificationsForm;
use backend\models\forms\SettingsSearchForm;
use backend\models\forms\SettingsTelegramForm;
use common\services\QueueManager;
use yii2mod\settings\actions\SettingsAction;

class SettingsController extends BaseController
{
    private $queueManager;

    public function __construct($id, $module, $config = [], QueueManager $queueManager)
    {
        $this->queueManager = $queueManager;
        parent::__construct($id, $module, $config);
    }

    public function actions()
    {
        $country = strtoupper(\Yii::$app->request->get('country', 'UA'));

        return [
            'top' => [
                'class' => SettingsAction::class,
                'on afterSave' => function ($event) {
                    $this->queueManager->createTopArticlesStatisticsJob();
                },
                'sectionName' => 'top-' . $country,
                'modelClass' => SettingsForm::class,
                'view' => 'top'
            ],
            'telegram' => [
                'class' => SettingsAction::class,
                'on afterSave' => function ($event) {
                    $this->queueManager->createTelegramApproveRequestsJob();
                },
                'modelClass' => SettingsTelegramForm::class,
                'view' => 'telegram'
            ],
            'push-notifications' => [
                'class' => SettingsAction::class,
                'sectionName' => 'pushes-' . $country,
                'modelClass' => SettingsPushNotificationsForm::class,
                'view' => 'push'
            ],
            'search' => [
                'class' => SettingsAction::class,
                'on afterSave' => function ($event) {
                    $this->queueManager->createTopQueriesCacheJob();
                },
                'sectionName' => 'search-' . $country,
                'modelClass' => SettingsSearchForm::class,
                'view' => 'search'
            ],
        ];
    }
}