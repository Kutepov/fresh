<?php namespace backend\models\search;

use backend\models\search\statistics\Calendar;
use yii\data\ActiveDataProvider;
use backend\models\Source;
use yii\db\Expression;
use yii\helpers\ArrayHelper;

/**
 * SourceSearch represents the model behind the search form about `\backend\models\Source`.
 */
class SourceSearch extends Source
{
    public $articlesAmount;
    use Calendar;

    public function rules(): array
    {
        return [
            [['id', 'group_id', 'enabled', 'ios_enabled', 'android_enabled', 'name', 'url', 'country', 'language', 'timezone', 'type', 'webview_js', 'banned_top', 'note', 'updated_at', 'dateInterval'], 'safe'],
            [['avg_news_freq', 'subscribers_count'], 'integer'],
            [['processed', 'default'], 'boolean']
        ];
    }

    public function search($params)
    {
        $this->load($params);
        $query = self::find();

        $query->select(array_filter([
            'sources.*',
            $this->dateInterval ? 'COUNT(distinct articles.id) as articlesAmount' : false
        ]));

        $query->joinWith(['languageModel', 'countries', 'urls']);

        if ($this->dateInterval) {
            $query->leftJoin('articles', [
                'AND',
                ['=', 'articles.source_id', new Expression('sources.id')],
                $this->dateCondition('articles.created_at')
            ]);
        }
        $query->groupBy('sources.id');

        if ($this->dateInterval) {
            $query->andFilterWhere($this->dateCondition('articles.created_at'));
        }


        $query->andFilterWhere([
            'avg_news_freq' => $this->avg_news_freq,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ]);


        $query->andFilterWhere(['like', 'sources.id', $this->id])
            ->andFilterWhere(['=', 'sources.default', $this->default])
            ->andFilterWhere(['=', 'sources.enabled', $this->enabled])
            ->andFilterWhere(['=', 'sources.ios_enabled', $this->ios_enabled])
            ->andFilterWhere(['=', 'sources.android_enabled', $this->android_enabled])
            ->andFilterWhere(['OR',
                ['like', 'sources.name', $this->name],
                ['like', 'sources.url', $this->name]
            ])
            ->andFilterWhere(['like', 'image', $this->image])
            ->andFilterWhere(['=', 'countries.code', $this->country])
            ->andFilterWhere(['=', 'language', $this->language])
            ->andFilterWhere(['=', 'timezone', $this->timezone])
            ->andFilterWhere(['=', 'type', $this->type])
            ->andFilterWhere(['=', 'banned_top', $this->banned_top])
            ->andFilterWhere(['processed' => $this->processed])
            ->andFilterWhere(['group_id' => $this->group_id]);

        return new ActiveDataProvider([
            'query' => $query,
            'sort' => [
                'defaultOrder' => [
                    'sources.created_at' => SORT_DESC
                ],
                'attributes' => [
                    'articlesAmount',
                    'sources.created_at',
                    'name',
                    'country' => [
                        'asc' => ['countries.name' => SORT_ASC],
                        'desc' => ['countries.name' => SORT_DESC],
                    ],
                    'language' => [
                        'asc' => ['languages.name' => SORT_ASC],
                        'desc' => ['languages.name' => SORT_DESC],
                    ],
                    'type',
                    'banned_top',
                    'ios_enabled',
                    'android_enabled',
                    'subscribers_count',
                    'enabled',
                    'processed',
                    'urls' => [
                        'asc' => ['count(sources_urls.id)' => SORT_ASC],
                        'desc' => ['count(sources_urls.id)' => SORT_DESC],
                    ]
                ]
            ]
        ]);
    }
}
