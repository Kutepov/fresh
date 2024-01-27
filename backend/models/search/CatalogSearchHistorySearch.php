<?php declare(strict_types=1);

namespace backend\models\search;

use common\models\CatalogSearchHistory;
use yii\data\ActiveDataProvider;
use yii\helpers\ArrayHelper;

class CatalogSearchHistorySearch extends CatalogSearchHistory
{
    public $country_code;

    public function rules()
    {
        return ArrayHelper::merge(parent::rules(), [
            ['country_code', 'string']
        ]);
    }

    public function search($params)
    {
        $query = self::find()->with('app');

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort' => [
                'defaultOrder' => [
                    'created_at' => SORT_DESC
                ]
            ],
            'pagination' => [
                'pageSize' => 50
            ]
        ]);

        if (!($this->load($params) && $this->validate($params))) {
            return $dataProvider;
        }

        if ($this->country_code) {
            $query->innerJoinWith('app')->andWhere([
                'apps.country' => $this->country_code
            ]);
        }

        $query->andFilterWhere([
            'AND',
            ['=', 'id', $this->id],
            ['LIKE', 'query', $this->query],
            ['=', 'section', $this->section],
            ['=', 'type', $this->type]
        ]);



        return $dataProvider;
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'app_id' => 'App ID',
            'query' => 'Запрос',
            'section' => 'Раздел',
            'type' => 'Тип',
            'created_at' => 'Дата запроса',
            'country_code' => 'Страна'
        ];
    }
}