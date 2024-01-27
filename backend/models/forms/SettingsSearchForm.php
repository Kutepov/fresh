<?php namespace backend\models\forms;

use yii\base\Model;

class SettingsSearchForm extends Model
{
    public $topQueriesPeriod;
    public $topQueriesAmount;
    public $topArticlesAmount;
    public $queriesLimit;

    public static function get($key, $country)
    {
        return \Yii::$app->settings->get('search-' . strtoupper($country), $key);
    }

    public function rules()
    {
        return [
            [['topArticlesAmount', 'topQueriesAmount', 'topQueriesPeriod', 'queriesLimit'], 'required'],
            [['topArticlesAmount', 'topQueriesAmount', 'topQueriesPeriod', 'queriesLimit'], 'integer'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'topArticlesAmount' => 'Количество топовых новостей',
            'topQueriesAmount' => 'Количество топовых запросов',
            'topQueriesPeriod' => 'Период пересчета топовых запросов',
            'queriesLimit' => 'Минимальное количество повторов запроса для попадания в топ'
        ];
    }

    public function attributeHints()
    {
        return [
            'topQueriesPeriod' => 'часы'
        ];
    }
}