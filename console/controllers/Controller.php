<?php namespace console\controllers;

use yii\helpers\Console;
use yii;

class Controller extends \yii\console\Controller
{
    protected $debug = false;
    protected $errorsCount = 0;

    public function options($actionID)
    {
        return [
            'debug'
        ];
    }

    public function beforeAction($action)
    {
        if ($this->debug) {
            define('CONSOLE_DEBUG', true);
        }

        return parent::beforeAction($action);
    }

    public function stdOutDebug($string, $color = null)
    {
        if ($this->debug) {
            $this->stdout($string, $color);
        }
    }

    public function stdErrDebug($string)
    {
        if ($this->debug) {
            $this->errorsCount++;
            $this->stderr($string);
        }
    }

    public function stdout($string, $color = null)
    {
        return parent::stdout(date('[H:i:s]') . ' ' . $string . PHP_EOL, $color);
    }

    public function stdFatalErr($string)
    {
        $this->errorsCount++;
        $this->stderr('[FATAL] ' . $string);
        exit;
    }

    public function stderr($string)
    {
        $this->errorsCount++;
        return parent::stderr(date('[H:i:s]') . ' ' . $string . PHP_EOL, Console::FG_RED);
    }
}