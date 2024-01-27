<?php namespace console\controllers\statistics;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use common\services\StatisticsService;
use console\controllers\Controller;
use yii\helpers\ArrayHelper;

class ArticlesController extends Controller
{
    private $service;
    public $force = false;

    public function options($actionID)
    {
        return ArrayHelper::merge(parent::options($actionID), [
            'force'
        ]);
    }

    public function __construct($id, $module, StatisticsService $service, $config = [])
    {
        $this->service = $service;
        parent::__construct($id, $module, $config);
    }

    public function actionStoreClicks($country = null)
    {
        $this->service->storeClicksStatistics($country);
    }

    public function actionStoreViews($country = null)
    {
        $this->service->storeViewsStatistics($country);
    }

    public function actionAggregate($country = null)
    {
        $this->service->aggregateStatistics(CarbonImmutable::now(), false, $country, $this->force);
    }

    public function actionAggregateClicks($country = null)
    {
        $this->service->aggregateStatistics(CarbonImmutable::now(), true, $country, $this->force);
    }

    public function actionClear()
    {
        $this->service->clearTemporaryRecoreds();
    }

    public function actionDailyCache($period = 1800)
    {
        $this->service->dailyCache($period);
    }

    public function actionCount($country = null)
    {
        echo 'clicks: ' . $this->service->getTemporaryClicksCount($country) . PHP_EOL;
        echo 'views: ' . $this->service->getTemporaryViewsCount($country) . PHP_EOL;
    }
}