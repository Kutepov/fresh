<?php namespace backend\models\search;

use yii\data\ActiveDataProvider;
use backend\models\Comment;

/**
 * CommentSearch represents the model behind the search form about `\backend\models\Comment`.
 */
class CommentSearch extends Comment
{
    public $article_text;
    public $created_at_filter;

    public function rules()
    {
        return [
            [['user_id', 'parent_comment_id', 'rating', 'answers_count', 'id'], 'integer'],
            [['enabled', 'article_id', 'text', 'article_text', 'created_at_filter'], 'safe'],
        ];
    }

    public function search($params)
    {
        $query = Comment::find()
            ->with('article');

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort' => [
                'defaultOrder' => [
                    'id' => SORT_DESC
                ],
            ]
        ]);

        if (!($this->load($params) && $this->validate())) {
            return $dataProvider;
        }

        if ($this->article_text) {
            $query->joinWith('article');
            $query->andFilterWhere(['like', 'articles.title', $this->article_text]);
        }

        if ($this->created_at_filter) {
            $date = new \DateTime($this->created_at_filter);
            $query->andFilterWhere(['between', 'created_at', $date->format('Y-m-d') . ' 00:00:00', $date->format('Y-m-d') . ' 23:59:59']);
        }

        if (!is_null($this->answers_count)) {
            $compare = $this->answers_count ? '>=' : '<=';
            $query->andFilterWhere([$compare, 'answers_count', $this->answers_count]);
        }


        $query->andFilterWhere([
            'id' => $this->id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'user_id' => $this->user_id,
            'rating' => $this->rating,
            'article_id' => $this->article_id
        ]);

        $query->andFilterWhere([
            'OR',
            ['=', 'parent_comment_id', $this->parent_comment_id],
            ['=', 'root_comment_id', $this->parent_comment_id]
        ]);

        $query->andFilterWhere(['like', 'enabled', $this->enabled])
            ->andFilterWhere(['like', 'article_id', $this->article_id])
            ->andFilterWhere(['like', 'text', $this->text]);

        return $dataProvider;
    }

    public function formName()
    {
        return '';
    }
}
