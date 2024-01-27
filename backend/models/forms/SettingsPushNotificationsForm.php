<?php namespace backend\models\forms;

use yii\base\Model;

class SettingsPushNotificationsForm extends Model
{
    public $enabled;
    public $minClicksCount;
    public $minCtr;
    public $newArticleTimeLimit;
    public $periodBetweenPushes;

    public function rules()
    {
        return [
            ['enabled', 'boolean'],
            [['minClicksCount', 'minCtr', 'newArticleTimeLimit'], 'required'],
            [['minClicksCount', 'minCtr', 'periodBetweenPushes'], 'integer', 'min' => 0],
            ['newArticleTimeLimit', 'integer', 'min' => 1]
        ];
    }

    public function attributeLabels()
    {
        return [
            'enabled' => 'Включить отправку',
            'minClicksCount' => 'Минимальное количество кликов для отправки пуша',
            'minCtr' => 'Минимальный CTR новости для отправки пуша',
            'newArticleTimeLimit' => 'Максимальное время для отправки пуша после публикации новости',
            'periodBetweenPushes' => 'Минимальный промежуток времени между отправкой пушей'
        ];
    }

    public function attributeHints()
    {
        return [
            'minCtr' => 'процент',
            'newArticleTimeLimit' => 'минуты',
            'periodBetweenPushes' => 'минуты'
        ];
    }
}