<?php namespace common\components\queue\jobs;

use yii\base\BaseObject;
use yii\queue\RetryableJobInterface;

abstract class Job extends BaseObject implements RetryableJobInterface
{
    public $debug = false;

    abstract public function execute($queue);

    public function getTtr()
    {
        return 600;
    }

    public function canRetry($attempt, $error)
    {
        return $attempt < 3;
    }
}