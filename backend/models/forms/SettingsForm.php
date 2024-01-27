<?php namespace backend\models\forms;

use yii\base\Model;

class SettingsForm extends Model
{
    public $topCtrUpdateForComment;
    public $topCtrUpdateForRating;
    public $topCtrUpdateForSharing;
    public $ctrPeriod;
    public $acceleratedNewsPeriod;
    public $ctrDecreaseStartHour;
    public $ctrDecreasePercent;
    public $maxTopPlace;
    public $minTopCtr;
    public $minClicksThreshold;
    public $newArticleTopTimeLimit;
    public $ctrDecreaseYesterdayPercent;
    public $topCalculationPeriod;

    public static function get($key, $country)
    {
        return \Yii::$app->settings->get('top-' . strtoupper($country), $key);
    }

    public function rules()
    {
        return [
            [['topCtrUpdateForComment', 'topCtrUpdateForRating', 'topCtrUpdateForSharing', 'acceleratedNewsPeriod', 'ctrPeriod', 'ctrDecreaseStartHour', 'ctrDecreasePercent', 'ctrDecreaseYesterdayPercent'], 'integer', 'min' => 0],
            ['maxTopPlace', 'integer', 'min' => 3],
            [['minTopCtr', 'minClicksThreshold', 'newArticleTopTimeLimit', 'topCalculationPeriod'], 'integer', 'min' => 1],
            [['topCtrUpdateForComment', 'topCtrUpdateForRating', 'ctrPeriod', 'ctrDecreaseStartHour', 'ctrDecreasePercent', 'minClicksThreshold', 'newArticleTopTimeLimit', 'topCalculationPeriod'], 'required']
        ];
    }

    public function attributeLabels()
    {
        return [
            'topCtrUpdateForComment' => 'Увеличение CTR за комментарий',
            'topCtrUpdateForRating' => 'Увеличение CTR за лайк/дизлайк',
            'topCtrUpdateForSharing' => 'Увеличение CTR за шеринг',
            'ctrPeriod' => 'Период для подсчета CTR новостей в топе',
            'ctrDecreaseStartHour' => 'С какого часа начинать уменьшение CTR',
            'ctrDecreasePercent' => 'На какой процент уменьшать CTR каждый час',
            'ctrDecreaseYesterdayPercent' => 'На какой процент уменьшать CTR каждый час для вчерашних новостей, начиная с сегодняшнего дня',
            'minTopCtr' => 'Минимальный CTR ТОПа для удержания новости в ТОПе',
            'minClicksThreshold' => 'Минимальное количество кликов для приоритета в ТОПе',
            'newArticleTopTimeLimit' => 'Максимальное время для попадании новости в ТОП после публикации',
            'topCalculationPeriod' => 'Период пересчета ТОПа'
        ];
    }

    public function attributeHints()
    {
        return [
            'topCtrUpdateForComment' => 'процент',
            'topCtrUpdateForRating' => 'процент',
            'topCtrUpdateForSharing' => 'процент',
            'acceleratedNewsPeriod' => 'минуты',
            'ctrPeriod' => 'последние N часов',
            'ctrDecreaseStartHour' => 'После публикации новости',
            'ctrDecreasePercent' => '0 - не уменьшать',
            'ctrDecreaseYesterdayPercent' => '0 - не уменьшать',
            'newArticleTopTimeLimit' => 'часы',
            'topCalculationPeriod' => 'минуты'
        ];
    }
}