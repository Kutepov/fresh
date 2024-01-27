<?php declare(strict_types=1);

namespace common\components\queue\jobs;

use common\models\App;
use common\services\CatalogSourcesService;

class InitAppSourcesSubscriptions extends Job
{
    public $appId;
    public $enabledSourcesUrls = [];

    public function execute($queue)
    {
        $service = \Yii::$container->get(CatalogSourcesService::class);
        if ($app = App::findOne($this->appId)) {
            $service->batchSubscribeToSourcesUrls($this->enabledSourcesUrls, false,$app);
        }
    }
}