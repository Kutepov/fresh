<?php namespace console\controllers\statistics;

use Carbon\CarbonImmutable;
use common\services\CategoriesService;
use common\services\HistoricalStatisticsService;

class HistoricalController extends \console\controllers\CategoriesController
{
    private $service;

    public function __construct($id, $module, CategoriesService $service, HistoricalStatisticsService $statisticsService, $config = [])
    {
        $this->service = $statisticsService;
        parent::__construct($id, $module, $service, $config);
    }

    public function actionIndex($date = null, $dateEnd = null)
    {
        if (!is_null($date)) {
            $date = CarbonImmutable::parse($date, 'UTC')->startOfDay();
        }

        if (!is_null($dateEnd)) {
            $dateEnd = CarbonImmutable::parse($dateEnd, 'UTC')->endOfDay();
        }

        $this->service->generate($date, $dateEnd);
    }

    public function actionPushNotifications($date = null, $dateEnd = null)
    {
        if (!is_null($date)) {
            $date = CarbonImmutable::parse($date, 'UTC')->startOfDay();
        }

        if (!is_null($dateEnd)) {
            $dateEnd = CarbonImmutable::parse($dateEnd, 'UTC')->endOfDay();
        }

        $this->service->generatePushNotifications($date, $dateEnd);
    }
}