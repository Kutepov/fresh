<?php namespace backend\models\search\statistics;

use backend\models\Article;
use backend\models\Comment;
use backend\models\User;
use common\models\aggregate\HistoricalStatistics;
use common\models\aggregate\HistoricalUsersStatistics;
use common\models\pivot\ArticleRating;
use common\models\App;
use common\models\pivot\CommentRating;
use common\models\statistics\ArticleClick;
use common\models\statistics\ArticleView;
use yii\data\ActiveDataProvider;
use yii\db\ActiveRecord;
use yii\db\Expression;
use yii\db\Query;

class CountrySearch extends ActiveRecord
{
    use Calendar;

    public $ctr;
    public $clicks;
    public $views;
    public $new_users;
    public $all_users;
    public $registrations;
    public $rating_comments;
    public $rating_articles;
    public $articles;
    public $comments;
    public $shares_count;

    public $platform;
    public $country;
    public $type;
    public $widget;
    public $previewType;

    public function attributeLabels()
    {
        return [
            'name' => 'Страна',
            'dateInterval' => 'Дата',
            'clicks' => 'Клики',
            'views' => 'Просмотры',
            'new_users' => 'Новые пользователи',
            'all_users' => 'Все пользователи',
            'platform' => 'ОС',
            'country' => 'Страна',
            'type' => 'Тип',
            'widget' => 'Виджет',
            'registrations' => 'Регистрации',
            'rating_comments' => 'Лайков/Дизлайков комментарии',
            'rating_articles' => 'Лайков/Дизлайков новости',
            'articles' => 'Новостей',
            'comments' => 'Комментариев',
            'previewType' => 'Размер картинок-превью'
        ];
    }

    public function rules()
    {
        return [
            [
                [
                    'dateInterval',
                    'clicks',
                    'views',
                    'new_users',
                    'all_users',
                    'platform',
                    'country',
                    'type',
                    'widget',
                    'registrations',
                    'rating_comments',
                    'rating_articles',
                    'articles',
                    'comments',
                    'previewType'
                ], 'safe']
        ];
    }

    public function search($params)
    {
        $this->load($params);

        if ($this->previewType === 'small') {
            $this->previewType = ['small', null];
        }

        if (!$this->dateInterval) {
            $this->createDefaultDatesInterval(0);
        }

        if ($this->type || $this->widget === 'same-articles') {
            $articlesFilterCondition = array_filter([
                'AND',
                ['=', 'sources.id', new Expression('articles.source_id')],
                $this->type ? ['=', 'sources.type', $this->type] : false,
                $this->widget === 'same-articles' ? ['IS NOT', 'articles.same_article_id', null] : false
            ]);
        }

        $query = (new Query())
            ->select([
                'ROUND(clicks/views*100, 1) as ctr',
                'countries.name',
                'clicks',
                'views',
                'shares_count',
                'new_users_amount as new_users',
                'all_users_amount as all_users',
                'registrations_amount as registrations',
                'rating_amount_comments as rating_comments',
                'rating_amount_articles as rating_articles',
                'comments_amount as comments',
                'articles_amount as articles',
            ])
            ->from('countries')
            ->groupBy('countries.code');

        //Регистрации
        $query->leftJoin([
            'registrations' => User::find()
                ->from(['registrations' => User::tableName()])
                ->select([
                    'COUNT(*) as registrations_amount',
                    'registrations.created_at',
                    'registrations.country_code',
                ])
                ->andWhere($this->dateCondition('registrations.created_at'))
                ->andFilterWhere([
                    'registrations.country' => $this->country,
                ])
                ->groupBy('registrations.country_code')
        ], 'registrations.country_code = countries.code');

        //Рейтинг комментариев
        $query->leftJoin([
            'rating_comments' => CommentRating::find()
                ->from(['rating_comments' => CommentRating::tableName()])
                ->select([
                    'COUNT(*) as rating_amount_comments',
                    'rating_comments.created_at',
                    'rating_comments.country',
                ])
                ->andFilterWhere([
                    'rating_comments.country' => $this->country,
                ])
                ->andFilterWhere($this->dateCondition('rating_comments.created_at'))
                ->groupBy('rating_comments.country')
        ], 'rating_comments.country = countries.code');

        //Рейтинг новостей
        $query->leftJoin([
            'rating_articles' => ArticleRating::find()
                ->from(['rating_articles' => ArticleRating::tableName()])
                ->select([
                    'COUNT(*) as rating_amount_articles',
                    'rating_articles.created_at',
                    'rating_articles.country',
                ])
                ->andFilterWhere([
                    'rating_articles.country' => $this->country,
                ])
                ->andFilterWhere($this->dateCondition('rating_articles.created_at'))
                ->groupBy('rating_articles.country')
        ], 'rating_articles.country = countries.code');

        //Кол-во комментариев
        $query = $query->leftJoin([
            'comments' => Comment::find()
                ->from(['comments' => Comment::tableName()])
                ->select([
                    'COUNT(*) as comments_amount',
                    'comments.created_at',
                    'comments.country',
                ])
                ->andFilterWhere([
                    'comments.country' => $this->country,
                ])
                ->andFilterWhere($this->dateCondition('comments.created_at'))
                ->groupBy('comments.country')
        ], 'comments.country = countries.code');


        //Новости
        $query = $query->leftJoin([
            'articles' => Article::find()
                ->from(['articles' => Article::tableName()])
                ->select([
                    'SUM(shares_count) as shares_count',
                    'COUNT(*) as articles_amount',
                    'articles.created_at',
                    'sources.country as sc',
                ])
                ->andFilterWhere($this->dateCondition('articles.created_at'))
                ->leftJoin('sources', 'sources.id = articles.source_id')
                ->groupBy('sc')
        ], 'sc = countries.code');


        if (!$this->type && !$this->previewType && !$this->widget) {
            $clicksQuery = HistoricalStatistics::find()
                ->from(['clicks' => HistoricalStatistics::tableName()])
                ->select([
                    'SUM(clicks) as clicks',
                    'date',
                    'clicks.country'
                ])
                ->andWhere($this->dateCondition())
                ->andFilterWhere([
                    'platform' => $this->platform,
                ])
                ->groupBy('clicks.country');


            $query->leftJoin([
                'articles_clicks' => $clicksQuery
            ], 'articles_clicks.country = countries.code');


            $viewsQuery = HistoricalStatistics::find()
                ->from(['views' => HistoricalStatistics::tableName()])
                ->select([
                    'SUM(views) as views',
                    'date',
                    'views.country'
                ])
                ->andWhere($this->dateCondition())
                ->andFilterWhere([
                    'platform' => $this->platform,
                ])
                ->groupBy('views.country');


            $query->leftJoin([
                'articles_views' => $viewsQuery
            ], 'articles_views.country = countries.code');


        }
        else {
            //Клики
            $clicksQuery = ArticleClick::find()
                ->select([
                    'COUNT(*) as clicks',
                    'date',
                    'articles_clicks.country',
                ])
                ->andWhere($this->dateCondition())
                ->andFilterWhere([
                    'platform' => $this->platform,
                    'articles_clicks.country' => $this->country,
                    'preview_type' => $this->previewType
                ])
                ->groupBy('articles_clicks.country');


            if ($this->widget && $this->widget !== 'same-articles') {
                $clicksQuery->andFilterWhere([
                    'widget' => $this->widget,
                ]);
            }

            if (isset($articlesFilterCondition)) {
                $clicksQuery
                    ->rightJoin('countries', [
                        'countries.id' => new Expression('articles_clicks.country'),
                    ])
                    ->rightJoin('articles', [
                        'articles.id' => new Expression('articles_clicks.article_id'),
                    ])
                    ->rightJoin('sources', $articlesFilterCondition);
            }

            $query->leftJoin([
                'articles_clicks' => $clicksQuery
            ], 'articles_clicks.country = countries.code');


            //Просмотры
            $viewsQuery = ArticleView::find()
                ->select([
                    'COUNT(*) as views',
                    'date',
                    'articles_views.country',
                ])
                ->andWhere($this->dateCondition())
                ->andFilterWhere([
                    'platform' => $this->platform,
                    'articles_views.country' => $this->country,
                    'preview_type' => $this->previewType
                ])
                ->groupBy('articles_views.country');

            if ($this->widget && $this->widget !== 'same-articles') {
                $viewsQuery->andFilterWhere([
                    'widget' => $this->widget,
                ]);
            }


            if (isset($articlesFilterCondition)) {
                $viewsQuery
                    ->rightJoin('countries', [
                        'countries.code' => new Expression('articles_views.country'),
                    ])
                    ->rightJoin('articles', [
                        'articles.id' => new Expression('articles_views.article_id'),
                    ])
                    ->rightJoin('sources', $articlesFilterCondition);
            }

            $query->leftJoin([
                'articles_views' => $viewsQuery
            ], 'articles_views.country = countries.code');
        }

        $allUsersQuery = HistoricalUsersStatistics::find()
            ->from(['all_users' => HistoricalUsersStatistics::tableName()])
            ->select([
                'SUM(users_amount) as all_users_amount',
                'date',
                'all_users.country'
            ])
            ->andFilterWhere([
                'platform' => $this->platform,
                'country' => $this->country,
            ])
            ->andWhere($this->dateCondition())
            ->groupBy('all_users.country');

        $query->leftJoin([
            'all_users' => $allUsersQuery
        ], 'all_users.country = countries.code');

        //Новые юзеры
        $query->leftJoin([
            'new_users' => App::find()
                ->from(['new_users' => App::tableName()])
                ->select([
                    'COUNT(*) as new_users_amount',
                    'date',
                    'new_users.country',
                ])
                ->andWhere($this->dateCondition())
                ->andFilterWhere([
                    'platform' => $this->platform,
                    'new_users.country' => $this->country,
                ])
                ->groupBy('new_users.country')
        ], 'new_users.country = countries.code');

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort' => [
                'attributes' => [
                    'name',
                    'clicks',
                    'views',
                    'new_users',
                    'all_users',
                    'registrations',
                    'rating_comments',
                    'rating_articles',
                    'shares_count',
                    'articles',
                    'comments',
                ],
                'defaultOrder' => [
                    'all_users' => SORT_DESC
                ],
            ],
        ]);

        return $dataProvider;
    }
}
