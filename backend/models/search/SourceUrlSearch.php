<?php namespace backend\models\search;

use backend\models\search\statistics\Calendar;
use yii\data\ActiveDataProvider;
use backend\models\SourceUrl;
use yii\db\Expression;

/**
 * SourceUrlSearch represents the model behind the search form about `\backend\models\SourceUrl`.
 */
class SourceUrlSearch extends SourceUrl
{
    use Calendar;

    public $country;
    public $articlesAmount;

    public function rules()
    {
        return [
            [['id', 'lock_id', 'avg_news_freq'], 'integer'],
            [['class', 'locked_at', 'dateInterval', 'timezone', 'url', 'category_id', 'source_id', 'category_name', 'last_scraped_at', 'last_scraped_article_date', 'last_scraped_article_date_disabled', 'note', 'created_at', 'updated_at', 'country'], 'safe'],
            [['enabled', 'ios_enabled', 'android_enabled', 'subscribers_count'], 'boolean']
        ];
    }

    public function search($params)
    {
        $query = self::find()->joinWith(['source']);
        $this->load($params);
        $query->select(array_filter([
            'sources_urls.*',
            $this->dateInterval ? 'COUNT(distinct articles.id) as articlesAmount' : false
        ]));

        if ($this->dateInterval) {
            $query->leftJoin('articles', [
                'AND',
                ['=', 'articles.source_url_id', new Expression('sources_urls.id')],
                $this->dateCondition('articles.created_at')
            ]);
        }

        $query->groupBy('sources_urls.id');

        if ($this->dateInterval) {
            $query->andFilterWhere($this->dateCondition('articles.created_at'));
        }
        $query->andFilterWhere([
            'sources_urls.id' => $this->id,
            'sources_urls.source_id' => $this->source_id,
            'locked_at' => $this->locked_at,
            'lock_id' => $this->lock_id,
            'last_scraped_at' => $this->last_scraped_at,
            'last_scraped_article_date' => $this->last_scraped_article_date,
            'avg_news_freq' => $this->avg_news_freq,
            'sources_urls.created_at' => $this->created_at,
            'sources_urls.updated_at' => $this->updated_at,
        ]);

        if ($this->country) {
            $query->byCountry($this->country);
        }

        $query->andFilterWhere(['like', 'class', $this->class])
            ->andFilterWhere(['=', 'sources_urls.enabled', $this->enabled == -1 ? 0 : $this->enabled])
            ->andFilterWhere(['=', 'sources_urls.ios_enabled', $this->ios_enabled == -1 ? 0 : $this->ios_enabled])
            ->andFilterWhere(['=', 'sources_urls.android_enabled', $this->android_enabled == -1 ? 0 : $this->android_enabled])
            ->andFilterWhere(['like', 'sources_urls.timezone', $this->timezone])
            ->andFilterWhere(['like', 'sources_urls.url', $this->url])
            ->andFilterWhere(['=', 'category_id', $this->category_id])
            ->andFilterWhere(['like', 'category_name', $this->category_name])
            ->andFilterWhere(['like', 'last_scraped_article_date_disabled', $this->last_scraped_article_date_disabled])
            ->andFilterWhere(['like', 'note', $this->note]);

        $query->groupBy('sources_urls.id');

        return new ActiveDataProvider([
            'query' => $query,
            'sort' => [
                'defaultOrder' => [
                    'sources_urls.created_at' => SORT_DESC
                ],
                'attributes' => [
                    'articlesAmount',
                    'sources_urls.created_at',
                    'subscribers_count',
                    'source' => [
                        'asc' => ['sources.name' => SORT_ASC],
                        'desc' => ['sources.name' => SORT_DESC],
                    ]
                ]
            ]
        ]);
    }
}
