<?php namespace backend\models\forms;

use yii\base\Model;

class SettingsTelegramForm extends Model
{
    public $minClicksCount;
    public $minCtr;
    public $newArticleTimeLimit;
    public $approvePeriod;
    public $lastUpdateId;

    public function rules()
    {
        return [
            [['minClicksCount', 'minCtr', 'newArticleTimeLimit'], 'required'],
            [['minClicksCount', 'minCtr', 'approvePeriod'], 'integer', 'min' => 0],
            ['newArticleTimeLimit', 'integer', 'min' => 1],
            ['lastUpdateId', 'integer']
        ];
    }

    public function attributeLabels()
    {
        return [
            'minClicksCount' => 'Минимальное количество кликов для отправки поста',
            'minCtr' => 'Минимальный CTR новости для отправки поста',
            'newArticleTimeLimit' => 'Максимальное время для отправки поста после публикации новости',
            'approvePeriod' => 'Период подтверждения заявок на вступление в канал'
        ];
    }

    public function attributeHints()
    {
        return [
            'minCtr' => 'процент',
            'newArticleTimeLimit' => 'минуты',
            'approvePeriod' => 'часы'
        ];
    }
}