<?php namespace backend\models\search\statistics;

use common\models\aggregate\HistoricalPushNotifications;
use yii\data\ActiveDataProvider;
use yii\db\Expression;
use yii\helpers\Html;

class PushNotifications extends HistoricalPushNotifications
{
    use Calendar;

    public $pushedAt;
    public $url;
    public $articleId;
    public $title;
    public $previewImage;
    public $source_id;
    public $category_id;
    public $clicks;
    public $views;
    public $sends;
    public $countryCode;
    public $flag;

    public function rules()
    {
        return [
            [['pushedAt', 'url', 'articleId', 'title', 'source_id', 'category_id', 'country', 'platform', 'articles_language', 'dateInterval'], 'safe']
        ];
    }

    public function search($params): ActiveDataProvider
    {
        $this->load($params);

        if (!$this->dateInterval) {
            $this->createDefaultDatesInterval(0);
        }

        $query = self::find()
            ->select([
                'articles.url',
                'articles.id as articleId',
                'articles.title',
                'articles.source_id',
                'articles.category_id',
                'articles.preview_image as previewImage',
                'SUM(clicked_amount) as clicks',
                'SUM(viewed_amount) as views',
                'MIN(historical_push_notifications.created_at) as pushedAt',
                'SUM(sent_amount) as sends',
                'historical_push_notifications.country as countryCode',
                'countries.image as flag',
            ])
            ->joinWith('article')
            ->leftJoin('countries', 'countries.code = historical_push_notifications.country')
            ->andFilterWhere([
                'historical_push_notifications.country' => $this->country,
                'historical_push_notifications.articles_language' => $this->articles_language,
                'historical_push_notifications.platform' => $this->platform,
                'source_id' => $this->source_id,
                'category_id' => $this->category_id
            ])
            ->andWhere($this->dateCondition('historical_push_notifications.date', true))
            ->groupBy('articleId');

        return new ActiveDataProvider([
            'query' => $query,
            'sort' => [
                'attributes' => [
                    'pushedAt',
                    'clicks',
                    'views',
                    'sends',
                    'ctr' => [
                        'asc' => new Expression('clicks / views * 100'),
                        'desc' => new Expression('clicks / views * 100 DESC'),
                    ],
                    'country'
                ],
                'defaultOrder' => [
                    'pushedAt' => SORT_DESC
                ]
            ]
        ]);
    }

    public function getCtr()
    {
        if ($this->views) {
            return round($this->clicks / $this->views * 100, 2) . '%';
        }

        return '0%';
    }

    public function pushesByCountry()
    {
        $amounts = self::find()
            ->select([
                new Expression('COUNT(distinct article_id) as amount'),
                'country',
                'articles_language'
            ])
            ->groupBy(['country', 'articles_language'])
            ->andWhere($this->dateCondition('historical_push_notifications.created_at'))
            ->asArray()
            ->all();

        $result = [];

        foreach ($amounts as  $amount) {
            $result[] = '<strong>' . implode('-', array_filter([$amount['country'], $amount['articles_language']])) . '</strong>: ' . Html::a($amount['amount'], array_filter(['statistics/push-notifications', 'PushNotifications[country]' => $amount['country'], 'PushNotifications[articles_language]' => $amount['articles_language'], 'PushNotifications[dateInterval]' => $this->dateInterval]), ['data-pjax' => 0]);
        }

        return implode(', ', $result);
    }

    public function attributeLabels(): array
    {
        return [
            'ctr' => 'CTR',
            'clicks' => 'Переходов',
            'views' => 'Доставлено',
            'sends' => 'Отправлено',
            'pushedAt' => 'Время отправки',
            'previewImage' => 'Превью',
            'title' => 'Новость',
            'dateInterval' => 'Дата',
            'country' => 'Страна',
            'platform' => 'Платформа',
            'articles_language' => 'Язык новостей',
            'source_id' => 'Источник',
            'category_id' => 'Категория'
        ];
    }
}