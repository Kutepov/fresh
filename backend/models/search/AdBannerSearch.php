<?php

namespace backend\models\search;

use Yii;
use yii\base\Model;
use yii\data\ActiveDataProvider;
use backend\models\AdBanner;

/**
 * AdBannerSearch represents the model behind the search form about `\backend\models\AdBanner`.
 */
class AdBannerSearch extends AdBanner
{
    public function rules()
    {
        return [
            [['id', 'position', 'repeat_factor', 'limit'], 'integer'],
            [['enabled', 'type', 'platform', 'country', 'provider', 'categories'], 'safe'],
        ];
    }

    public function scenarios()
    {
        // bypass scenarios() implementation in the parent class
        return Model::scenarios();
    }

    public function search($params)
    {
        $query = AdBanner::find();

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        if (!($this->load($params) && $this->validate())) {
            return $dataProvider;
        }

        $query->andFilterWhere([
            'id' => $this->id,
            'position' => $this->position,
            'repeat_factor' => $this->repeat_factor,
            'limit' => $this->limit,
            'type' => $this->type,
        ]);

        $query->andFilterWhere(['like', 'enabled', $this->enabled])
            ->andFilterWhere(['like', 'platform', $this->platform])
            ->andFilterWhere(['like', 'country', $this->country])
            ->andFilterWhere(['like', 'provider', $this->provider])
            ->andFilterWhere(['like', 'categories', $this->categories]);

        return $dataProvider;
    }
}
