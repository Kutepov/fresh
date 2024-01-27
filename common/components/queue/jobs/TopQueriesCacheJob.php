<?php namespace common\components\queue\jobs;

use common\services\SearchService;
use common\services\StatisticsService;

class TopQueriesCacheJob extends Job
{
    public function execute($queue)
    {
        $service = \Yii::$container->get(SearchService::class);
        $service->cacheAllTopQueries();
    }
}