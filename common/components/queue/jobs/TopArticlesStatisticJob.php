<?php namespace common\components\queue\jobs;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use common\services\StatisticsService;

class TopArticlesStatisticJob extends Job
{
    public function execute($queue)
    {
        $service = \Yii::$container->get(StatisticsService::class);
        $service->aggregateStatistics(CarbonImmutable::now('UTC'));
    }
}