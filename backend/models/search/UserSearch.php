<?php

namespace backend\models\search;

use yii\data\ActiveDataProvider;
use backend\models\User;

/**
 * UserSearch represents the model behind the search form about `\backend\models\User`.
 */
class UserSearch extends User
{
    public $created_at_filter;

    public function rules()
    {
        return [
            [['id', 'status'], 'integer'],
            [['name', 'email', 'firstname', 'lastname', 'created_at_filter', 'geo', 'platform'], 'safe'],
        ];
    }

    public function search($params)
    {
        $query = User::find()
            ->with('comments');

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort' => [
                'defaultOrder' => ['id' => SORT_DESC]
            ]
        ]);

        if (!($this->load($params) && $this->validate())) {
            return $dataProvider;
        }

        if ($this->created_at_filter) {
            $date = new \DateTime($this->created_at_filter);
            $query->andFilterWhere(['between', 'created_at', $date->format('Y-m-d') . ' 00:00:00', $date->format('Y-m-d') . ' 23:59:59']);
        }

        $query->andFilterWhere([
            'status' => $this->status,
            'id' => $this->id,
            'geo' => $this->geo,
            'platform' => $this->platform
        ]);

        $query->andFilterWhere(['like', 'name', $this->name])
            ->andFilterWhere(['like', 'email', $this->email]);

        return $dataProvider;
    }
}
