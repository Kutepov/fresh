<?php declare(strict_types=1);

namespace common\components\queue\jobs;

use common\services\CategoriesService;

class CalcArticlesInCategoriesJob extends Job
{

    public function execute($queue)
    {
        $service = \Yii::$container->get(CategoriesService::class);
        $service->calcArticlesInCategories();
    }
}