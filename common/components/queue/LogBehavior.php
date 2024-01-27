<?php namespace common\components\queue;

use yii\queue\Queue;

class LogBehavior extends \yii\queue\LogBehavior
{
    /**
     * @inheritdoc
     */
    public function events()
    {
        return [
            Queue::EVENT_AFTER_ERROR => 'afterError',
            \yii\queue\cli\Queue::EVENT_WORKER_START => 'workerStart',
            \yii\queue\cli\Queue::EVENT_WORKER_STOP => 'workerStop',
        ];
    }
}