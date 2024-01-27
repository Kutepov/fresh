<?php namespace backend\models\search\statistics;

use backend\models\Article;
use backend\models\forms\SettingsForm;
use Carbon\Carbon;
use common\models\aggregate\ArticlesStatistics;
use common\models\Country;
use common\models\statistics\ArticleClick;
use common\models\statistics\ArticleView;
use yii\data\ActiveDataProvider;
use yii\db\Expression;

class ByNewsSearch extends Article
{
    use Calendar;

    public $clicks;
    public $views;
    public $country_name;
    public $flag;
    public $country_id;
    public $category_id;
    public $platform;
    public $ctr;
    public $ctr_last;
    public $ctr_top_modified;
    public $top_position;
    public $accelerated_at;
    public $CTR_percent_to_decrease;
    public $hours_diff;
    public $clicked_top;
    public $showed_top;
    public $clicked;
    public $showed;
    public $clicks_feed;
    public $clicks_top;
    public $clicks_similar_articles;
    public $views_feed;
    public $views_top;
    public $views_similar_articles;
    public $ctr_feed;
    public $ctr_top;
    public $ctr_similar_articles;
    public $language;
    public $ctr_common_modified;
    public $ctr_common;
    public $ctrFrom;

    public $ctr_ratings_count;
    public $ctr_comments_count;
    public $ctr_shares_count;

    public function attributeLabels()
    {
        if ($this->country_id) {
            $ctrPeriod = SettingsForm::get('ctrPeriod', $this->country_id);
        }
        else {
            $ctrPeriod = '?';
        }

        $labels = parent::attributeLabels();
        return array_merge($labels, [
            'top_position' => 'Топ',
            'country_name' => 'Страна',
            'country_id' => 'Страна',
            'dateInterval' => 'Даты',
            'platform' => 'ОС',
            'ctr' => 'CTR<br>за все время',
            'ctr_last' => 'CTR ФИД<br />за ' . $ctrPeriod . ' часа',
            'ctr_last_calc' => 'CTR ФИД<br />Расчет',
            'ctr_common_modified' => 'CTR общий<br />за ' . $ctrPeriod . ' часа',
            'ctr_common_calc' => 'CTR общий<br />Расчет',
            'rating' => 'Рейтинг',
            'date' => 'Дата',
            'language' => 'Язык новостей'
        ]);
    }

    public function rules()
    {
        return [
            [
                [
                    'title',
                    'clicks',
                    'views',
                    'country_name',
                    'country_id',
                    'dateInterval',
                    'created_at',
                    'platform',
                    'category_id',
                    'source_id',
                    'views_date',
                    'clicks_date',
                    'language',
                    'ctr',
                    'ctr_top',
                    'top_position',
                    'id',
                    'ctrFrom',
                    'source_url_id'
                ],
                'safe'
            ],
        ];
    }

    public function search($params)
    {
        $query = self::find()
            ->select([
                'ctr_ratings_count',
                'ctr_shares_count',
                'ctr_comments_count',
                'articles.source_id',
                'articles.shares_count',
                'articles.created_at as created_at',
                'articles.id as id',
                'articles.url as url',
                'preview_image',
                'title',
                'countries.name as country_name',
                'countries.image as flag',
                'clicks',
                'views',
                'clicks_feed',
                'clicks_top',
                'clicks_similar_articles',
                'views_feed',
                'CTR_common_modified as ctr_common_modified',
                'CTR_common as ctr_common',
                'views_top',
                'views_similar_articles',
                'ROUND(clicks/views*100) as ctr',
                'ROUND(clicks_feed/views_feed*100) as ctr_feed',
                'ROUND(clicks_top/views_top*100) as ctr_top',
                'ROUND(clicks_similar_articles/views_similar_articles*100) as ctr_similar_articles',
                'comments_count',
                'ctr_modified as ctr_last',
                'rating',
                'top_position',
                'accelerated_at',
                'articles_statistics.ratings_count as ratings_count',
                'CTR_percent_to_decrease',
                'hours_diff',
                'clicked_top',
                'showed_top',
                'clicked',
                'showed',
                'articles.source_url_id',
                'articles.source_id'
            ]);

        $this->load($params);
        if (!$this->dateInterval) {
            $this->createDefaultDatesInterval(0);
        }

        $query->andWhere($this->dateCondition('articles.created_at'));

        $query->joinWith('source');
        $query->leftJoin('countries', 'sources.country = countries.code');

        $query->leftJoin([
            'articles_statistics' => ArticlesStatistics::find()
                ->select([
                    'articles_statistics.ratings_count as ctr_ratings_count',
                    'articles_statistics.shares_count as ctr_shares_count',
                    'articles_statistics.comments_count as ctr_comments_count',
                    'article_id',
                    'CTR_common_modified',
                    'CTR_common',
                    'ctr_modified',
                    'top_position',
                    'accelerated_at',
                    'ratings_count',
                    'CTR_percent_to_decrease',
                    'hours_diff',
                    'clicked_top',
                    'showed_top',
                    'clicked',
                    'showed',
                    'clicks_common as clicks',
                    'views_common as views',
                    'clicks_feed',
                    'clicks_top',
                    'clicks_similar_articles',
                    'views_feed',
                    'views_top',
                    'views_similar_articles'
                ])
                ->groupBy('articles_statistics.article_id')
                ->andWhere($this->dateCondition('articles_statistics.created_at'))
        ], 'articles_statistics.article_id = articles.id');

        $query->groupBy('articles.id');

        if (!$this->country_id && !$this->source_url_id) {
            $query->andWhere(['>', 'views', 0]);
        }

        if ($this->top_position) {
            $query->andWhere([
                'OR',
                ['<=', 'top_position', $this->top_position],
                ['IS NOT', 'accelerated_at', null]
            ]);
        }

        $query->andFilterWhere(['like', 'title', $this->title])
            ->andFilterWhere(['countries.code' => $this->country_id])
            ->andFilterWhere(['articles.source_url_id' => $this->source_url_id])
            ->andFilterWhere(['sources.id' => $this->source_id])
            ->andFilterWhere(['articles.category_id' => $this->category_id])
            ->andFilterWhere(['sources.language' => $this->language])
            ->andFilterWhere(['articles.id' => $this->id])
            ->andFilterWhere(['>=', new Expression('clicks_feed/views_feed*100'), $this->ctrFrom]);

        return new ActiveDataProvider([
            'query' => $query,
            'sort' => [
                'attributes' => [
                    'title',
                    'clicks',
                    'views',
                    'country_name',
                    'created_at',
                    'rating',
                    'comments_count',
                    'ctr',
                    'ctr_last',
                    'ctr_common_modified',
                    'ctr_common',
                    'clicks_feed',
                    'shares_count',
                    'clicks_top',
                    'clicks_similar_articles',
                    'views_feed',
                    'views_top',
                    'views_similar_articles',
                    'ctr_feed',
                    'ctr_top',
                    'ctr_similar_articles',
                    'top_position' => [
                        'asc' => new Expression(' accelerated_at DESC, -top_position DESC'),
                        'desc' => new Expression('accelerated_at, -top_position'),
                    ]
                ],
                'defaultOrder' => [
                    'created_at' => SORT_DESC
                ],
            ],
        ]);
    }

    public function getCtrCalcTip(): ?string
    {
        if (!$this->country_id) {
            return 'Выберите страну';
        }

        $ctrUpForRatings = SettingsForm::get('topCtrUpdateForRating', $this->country_id);
        $ctrUpForComments = SettingsForm::get('topCtrUpdateForComment', $this->country_id);
        $ctrUpForShares = SettingsForm::get('topCtrUpdateForSharing', $this->country_id);
        $ctrDecreaseStartHour = SettingsForm::get('ctrDecreaseStartHour', $this->country_id);

        $result = [];

        if (!$this->ctr_common_modified) {
            return null;
        }

        $defaultCtr = $this->ctr_common;
        $ctr = $defaultCtr;

        $result[] = 'Исходный CTR: <b>' . round($ctr) . '%</b>';

        if ($this->ctr_comments_count && $ctrUpForComments) {
            $ctr += ($defaultCtr / 100 * ($ctrUpForComments * $this->ctr_comments_count));
            $result[] = '<b class="text-success">+</b> ' . $this->ctr_comments_count . ' комм. = <b>' . round($ctr) . '%</b>';
        }

        if ($this->ctr_ratings_count && $ctrUpForRatings) {
            $ctr += ($defaultCtr / 100 * ($ctrUpForRatings * $this->ctr_ratings_count));
            $result[] = '<b class="text-success">+</b> ' . $this->ctr_ratings_count . ' рейт. = <b>' . round($ctr) . '%</b>';
        }

        if ($this->ctr_shares_count && $ctrUpForShares) {
            $ctr += ($defaultCtr / 100 * ($ctrUpForShares * $this->ctr_shares_count));
            $result[] = '<b class="text-success">+</b> ' . $this->ctr_shares_count . ' шер. = <b>' . round($ctr) . '%</b>';
        }

        if ($this->CTR_percent_to_decrease) {
            $ctr -= $ctr / 100 * $this->CTR_percent_to_decrease;
            $result[] = '<b class="text-danger">-</b> ' . ($this->hours_diff - $ctrDecreaseStartHour) . ' час. = <b>' . round($ctr) . '%</b>';
        }

        $articleCreatedAt = $this->created_at->setTimezone($this->source->timezone);
        $today = Carbon::now($this->source->timezone)->startOfDay();
        $now = Carbon::now($this->source->timezone);

        if ($articleCreatedAt < $today && ($diffInHours = $today->diffInHours($now)) >= 1) {
            $result[] = '<b class="text-danger">-</b> ' . $diffInHours . ' час. (с 00:00) = <b>' . round($this->ctr_common_modified) . '%</b>';
        }

        if (count($result) === 1) {
            return null;
        }

        return implode('<br />', $result);
    }

    public function getSelectedCountry(): ?Country
    {
        return Country::findByCode($this->country_id);
    }
}