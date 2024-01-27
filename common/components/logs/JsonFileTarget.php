<?php namespace common\components\logs;

use Carbon\Carbon;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;
use yii\helpers\VarDumper;
use yii\log\Logger;

class JsonFileTarget extends \UrbanIndo\Yii2\JsonFileTarget\JsonFileTarget
{
    private const RESTRICTED_KEYS = [
        'TOKEN', 'ACCOUNT', 'SECRET', 'PRIVATE', 'KEY'
    ];

    protected static function formatTime($timestamp): string
    {
        $dateTime = Carbon::createFromTimestamp($timestamp);
        $dateTime->setTimezone('Europe/Moscow');
        return $dateTime->toDateTimeString();
    }

    /**
     * @param mixed $log
     * @return string The formatted message
     */
    public function formatMessage($log): string
    {
        list($message, $level, $category, $timestamp) = $log;
        $traces = self::formatTracesIfExists($log);

        $text = $this->parseMessage($message);
        $basicInfo = [
            'timestamp' => self::formatTime($timestamp),
            'level' => Logger::getLevelName($level),
            'category' => $category,
            'traces' => implode("\n", $traces),
            'message' => $text,
        ];
        $appInfo = $this->getAppInfo($log);
        $formatted = array_merge($basicInfo, $appInfo);

        if ($this->includeContext) {
            $context = ArrayHelper::getValue($log, 'context');
            $context = array_map(static function ($variable) {
                foreach ($variable as $k => $v) {
                    foreach (self::RESTRICTED_KEYS as $key) {
                        if (stripos($k, $key) !== false) {
                            unset($variable[$k]);
                        }
                    }

                }
                return $variable;
            }, $context);
            $formatted = array_merge($formatted, [
                'context' => VarDumper::export($context)
            ]);
        }
        return Json::encode($formatted);
    }
}