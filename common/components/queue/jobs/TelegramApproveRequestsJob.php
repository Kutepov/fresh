<?php namespace common\components\queue\jobs;

use common\contracts\Poster;

class TelegramApproveRequestsJob extends Job
{
    public function execute($queue)
    {
        $service = \Yii::$container->get(Poster::class);
        $service->approveRequests();
    }
}