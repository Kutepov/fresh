<?php namespace backend\models\search;

use yii\data\ActiveDataProvider;
use backend\models\Category;

/**
 * CategorySearch represents the model behind the search form about `\backend\models\Category`.
 */
class CategorySearch extends Category
{
    public $listCountries;

    public function rules()
    {
        return [
            [['id', 'name', 'listCountries'], 'safe'],
            [['priority'], 'integer'],
        ];
    }

    public function search($params)
    {
        $query = Category::find()->multilingual();
        $this->load($params);

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort' => [
                'attributes' => [
                    'name', 'id', 'priority', 'jp_priority'
                ],
                'defaultOrder' => [
                    $this->listCountries === 'JP' ? 'jp_priority' : 'priority' => SORT_ASC
                ]
            ],
            'pagination' => [
                'pageSize' => 100
            ]
        ]);

        $query->groupBy('categories.id');
        $query->leftJoin('categories_lang', 'categories_lang.owner_id = categories.id');

        if (!$this->validate()) {
        return $dataProvider;
    }

        $query->andFilterWhere([
            'priority' => $this->priority,
        ]);

        $query->andFilterWhere(['like', 'id', $this->id])
            ->andFilterWhere(['like', 'title', $this->name]);

        if ($this->listCountries) {
            $query->joinWith('countries');
            $query->andFilterWhere(['country' => $this->listCountries]);

            $dataProvider->models = array_map(function (Category $category) {
                $category->country = $this->listCountries;
                return $category;
            }, $dataProvider->models);
        }

        return $dataProvider;
    }
}
