<?php namespace backend\models\search\statistics;

use backend\models\Article;
use backend\models\Comment;
use common\components\caching\Cache;
use common\models\aggregate\HistoricalStatistics;
use common\models\aggregate\HistoricalUsersStatistics;
use common\models\pivot\ArticleRating;
use backend\models\User;
use common\models\App;
use common\models\pivot\CommentRating;
use common\models\statistics\ArticleClick;
use common\models\statistics\ArticleView;
use yii\caching\ChainedDependency;
use yii\caching\ExpressionDependency;
use yii\caching\TagDependency;
use yii\data\ActiveDataProvider;
use yii\db\ActiveRecord;
use yii\db\Expression;
use yii\db\Query;

class CommonSearch extends ActiveRecord
{
    use Calendar;

    public $clicks;
    public $views;
    public $new_users;
    public $all_users;
    public $registrations;
    public $rating_comments;
    public $rating_articles;
    public $articles;
    public $comments;

    public $platform;
    public $country_id;
    public $type;
    public $widget;
    public $language;
    public $shares_count;

    public $previewType;
    public $categoryId;

    public function attributeLabels()
    {
        return [
            'dateInterval' => 'Дата',
            'clicks' => 'Клики',
            'views' => 'Показы',
            'new_users' => 'Новые пользователи',
            'all_users' => 'Все пользователи',
            'platform' => 'ОС',
            'country_id' => 'Страна',
            'type' => 'Тип',
            'widget' => 'Виджет',
            'registrations' => 'Регистрации',
            'rating_comments' => 'Лайков/Дизлайков комментарии',
            'rating_articles' => 'Лайков/Дизлайков новости',
            'articles' => 'Новостей',
            'comments' => 'Комментариев',
            'language' => 'Язык новостей',
            'previewType' => 'Размер картинок-превью',
            'categoryId' => 'Категория'
        ];
    }

    public function rules()
    {
        return [
            [
                [
                    'dateInterval',
                    'clicks', 'views',
                    'new_users',
                    'all_users',
                    'platform',
                    'country_id',
                    'language',
                    'type',
                    'widget',
                    'registrations',
                    'rating_comments',
                    'rating_articles',
                    'articles',
                    'comments',
                    'previewType',
                    'categoryId'
                ], 'safe']
        ];
    }

    public function search($params, $cachePeriod = 900, $refreshCache = false)
    {
        $this->load($params);

        if ($this->previewType === 'small') {
            $this->previewType = ['small', null];
        }

        if (!$this->dateInterval) {
            $this->createDefaultDatesInterval();
        }

        if ($this->type || $this->widget === 'same-articles' || $this->language) {
            $articlesFilterCondition = array_filter([
                'AND',
                $this->language ? ['=', 'sources.language', $this->language] : false,
                ['=', 'sources.id', new Expression('articles.source_id')],
                $this->type ? ['=', 'sources.type', $this->type] : false,
                $this->widget === 'same-articles' ? ['IS NOT', 'articles.same_article_id', null] : false
            ]);
        }

        $query = (new Query())
            ->select([
                'calendar.date',
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
            ->from('calendar')
            ->andWhere($this->dateCondition('calendar.date', true))
            ->groupBy('calendar.date');


        if (!$this->widget && !$this->type && !$this->previewType) {
            $clicksQuery = HistoricalStatistics::find()
                ->from(['hist_clicks' => HistoricalStatistics::tableName()])
                ->select([
                    'SUM(hist_clicks.clicks) as clicks',
                    'date'
                ])
                ->andWhere($this->dateCondition())
                ->andFilterWhere([
                    'hist_clicks.platform' => $this->platform,
                    'hist_clicks.country' =>$this->country_id,
                    'hist_clicks.category_id' => $this->categoryId,
                    'hist_clicks.articles_language' => $this->language
                ])
                ->groupBy('hist_clicks.date');


            $viewsQuery = HistoricalStatistics::find()
                ->from(['hist_views' => HistoricalStatistics::tableName()])
                ->select([
                    'SUM(hist_views.views) as views',
                    'date'
                ])
                ->andWhere($this->dateCondition())
                ->andFilterWhere([
                    'hist_views.platform' => $this->platform,
                    'hist_views.country' =>$this->country_id,
                    'hist_views.category_id' => $this->categoryId,
                    'hist_views.articles_language' => $this->language
                ])
                ->groupBy('hist_views.date');



            $query->leftJoin([
                'hist_clicks' => $clicksQuery
            ], 'hist_clicks.date = calendar.date');


            $query->leftJoin([
                'hist_views' => $viewsQuery
            ], 'hist_views.date = calendar.date');
        }
        else {
            //Клики
            $clicksQuery = ArticleClick::find()
                ->select([
                    'COUNT(*) as clicks',
                    'date',
                ])
                ->andWhere($this->dateCondition())
                ->andFilterWhere([
                    'platform' => $this->platform,
                    'articles_clicks.country' => $this->country_id,
                    'preview_type' => $this->previewType,
                    'category_id' => $this->categoryId
                ])
                ->groupBy('articles_clicks.date');

            if ($this->widget && $this->widget !== 'same-articles') {
                $clicksQuery->andFilterWhere([
                    'widget' => $this->widget,
                ]);
            }

            if (isset($articlesFilterCondition)) {
                $clicksQuery
                    ->rightJoin('articles', [
                        'articles.id' => new Expression('articles_clicks.article_id'),
                    ])
                    ->rightJoin('sources', $articlesFilterCondition);
            }

            $query->leftJoin([
                'articles_clicks' => $clicksQuery
            ], 'articles_clicks.date = calendar.date');

            //Просмотры
            $viewsQuery = ArticleView::find()
                ->select([
                    'COUNT(*) as views',
                    'date',
                ])
                ->andWhere($this->dateCondition())
                ->andFilterWhere([
                    'platform' => $this->platform,
                    'articles_views.country' => $this->country_id,
                    'preview_type' => $this->previewType,
                    'category_id' => $this->categoryId
                ])
                ->groupBy('articles_views.date');

            if ($this->widget && $this->widget !== 'same-articles') {
                $viewsQuery->andFilterWhere([
                    'widget' => $this->widget,
                ]);
            }

            if (isset($articlesFilterCondition)) {
                $viewsQuery
                    ->rightJoin('articles', [
                        'articles.id' => new Expression('articles_views.article_id'),
                    ])
                    ->rightJoin('sources', $articlesFilterCondition);
            }

            $query->leftJoin([
                'articles_views' => $viewsQuery
            ], 'articles_views.date = calendar.date');
        }

        //Новости
        $query = $query->leftJoin([
            'articles' => Article::find()
                ->from(['articles' => Article::tableName()])
                ->select([
                    'SUM(shares_count) as shares_count',
                    'COUNT(*) as articles_amount',
                    'DATE_FORMAT(articles.created_at, "%Y-%m-%d") as articles_date'
                ])
                ->andWhere($this->dateCondition('articles.created_at'))
                ->groupBy('articles_date')
        ], 'articles_date = calendar.date');

        //Кол-во комментариев
        $query = $query->leftJoin([
            'comments' => Comment::find()
                ->from(['comments' => Comment::tableName()])
                ->select([
                    'COUNT(*) as comments_amount',
                    'DATE_FORMAT(comments.created_at, "%Y-%m-%d") as comments_date'
                ])
                ->andFilterWhere([
                    'comments.country' => $this->country_id,
                ])
                ->groupBy('comments_date')
        ], 'comments_date = calendar.date');

        //Рейтинг комментариев
        $query->leftJoin([
            'rating_comments' => CommentRating::find()
                ->from(['rating_comments' => CommentRating::tableName()])
                ->select([
                    'COUNT(*) as rating_amount_comments',
                    'DATE_FORMAT(rating_comments.created_at, "%Y-%m-%d") as rating_comments_date',
                    'rating_comments.country',
                ])
                ->andFilterWhere([
                    'rating_comments.country' => $this->country_id,
                ])
                ->groupBy('rating_comments_date')
        ], 'rating_comments_date = calendar.date');

        //Рейтинг новостей
        $query->leftJoin([
            'rating_articles' => ArticleRating::find()
                ->from(['rating_articles' => ArticleRating::tableName()])
                ->select([
                    'COUNT(*) as rating_amount_articles',
                    'DATE_FORMAT(rating_articles.created_at, "%Y-%m-%d") as rating_articles_date',
                    'rating_articles.country',
                ])
                ->andFilterWhere([
                    'rating_articles.country' => $this->country_id,
                ])
                ->groupBy('rating_articles_date')
        ], 'rating_articles_date = calendar.date');

        //Регистрации
        $query->leftJoin([
            'registrations' => User::find()
                ->from(['registrations' => User::tableName()])
                ->select([
                    'COUNT(*) as registrations_amount',
                    'DATE_FORMAT(registrations.created_at, "%Y-%m-%d") as registration_date',
                    'registrations.country_code',
                ])
                ->andFilterWhere([
                    'registrations.platform' => $this->platform,
                    'registrations.country_code' => $this->country_id,
                ])
                ->groupBy('registration_date')
        ], 'registration_date = calendar.date');


        //Новые юзеры
        $query->leftJoin([
            'new_users' => App::find()
                ->from(['new_users' => App::tableName()])
                ->select([
                    'COUNT(*) as new_users_amount',
                    'date'
                ])
                ->andFilterWhere([
                    'platform' => $this->platform,
                    'new_users.country' => $this->country_id,
                ])
                ->andWhere($this->dateCondition())
                ->groupBy('new_users.date')
        ], 'new_users.date = calendar.date');

        $allUsersQuery = HistoricalUsersStatistics::find()
            ->from(['all_users'=> HistoricalUsersStatistics::tableName()])
            ->select([
                'SUM(users_amount) as all_users_amount',
                'date'
            ])
            ->andFilterWhere([
                'platform' => $this->platform,
                'all_users.country' => $this->country_id,
            ])
            ->andWhere($this->dateCondition())
            ->groupBy('all_users.date');

        $query->leftJoin([
            'all_users' => $allUsersQuery
        ], 'all_users.date = calendar.date');

        $query = $query->cache($cachePeriod);

        $dataProvider = new ActiveDataProvider([
            'query' => &$query,
            'sort' => [
                'attributes' => [
                    'date',
                    'clicks',
                    'views',
                    'new_users',
                    'all_users',
                    'registrations',
                    'rating_comments',
                    'rating_articles',
                    'articles',
                    'comments',
                    'shares_count'
                ],
                'defaultOrder' => [
                    'date' => SORT_DESC
                ],
            ],
        ]);

        $dataProvider->setTotalCount($this->getDatesIntervalDaysAmount());

        if (($pagination = $dataProvider->getPagination()) !== false) {
            $pagination->totalCount = $dataProvider->getTotalCount();
            if ($pagination->totalCount === 0) {
                return [];
            }
            $query->limit($pagination->getLimit())->offset($pagination->getOffset());
        }
        if (($sort = $dataProvider->getSort()) !== false) {
            $query->addOrderBy($sort->getOrders());
        }

        if ($refreshCache) {
            $query->noCache();
        }

        return $dataProvider;
    }
}
