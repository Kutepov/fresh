<?php namespace buzz\components;

class View extends \yii\web\View
{
    public $deviceDetector;

    public function init()
    {
        $this->deviceDetector = new \Detection\MobileDetect();
        parent::init();
    }
}