<?php namespace common\services;

use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use common\models\aggregate\HistoricalPushNotifications;
use common\models\aggregate\HistoricalStatistics;
use common\models\aggregate\HistoricalUsersStatistics;
use common\models\App;
use common\models\Article;
use common\models\Country;
use common\models\PushNotification;
use common\models\Source;
use common\models\statistics\ArticleClick;
use common\models\statistics\ArticleView;
use yii\db\Expression;

class HistoricalStatisticsService
{
    public function generate(?CarbonImmutable $dateStart = null, ?CarbonImmutable $dateEnd = null)
    {
        if (is_null($dateStart)) {
            $dateStart = CarbonImmutable::today('UTC');
        }

        if (is_null($dateEnd)) {
            $dateEnd = $dateStart->endOfDay();
        }

        $period = CarbonPeriod::create($dateStart, $dateEnd);

        foreach ($period as $date) {
            $countries = Country::find()->all();
            foreach ($countries as $country) {
                $batch = [];
                $batchUsers = [];
                foreach ([App::PLATFORM_WEB, App::PLATFORM_IOS, App::PLATFORM_ANDROID] as $platform) {
                    $usersAmount = ArticleView::find()
                        ->select('COUNT(DISTINCT app_id)')
                        ->where([
                            'date' => $date->toDateString(),
                            'country' => $country->code,
                            'platform' => $platform
                        ])
                        ->scalar();

                    if ($usersAmount) {
                        $batchUsers[] = [
                            'date' => $date->toDateString(),
                            'platform' => $platform,
                            'country' => $country->code,
                            'users_amount' => $usersAmount
                        ];
                    }

                    foreach ($country->articlesLanguages ?: [null] as $language) {
                        foreach ($country->categories as $category) {
                            $sourcesIds = Source::find()->byCountry($country->code)->byLanguage($language->code ?? null)->column();
                            $articlesIds = Article::find()->select('id')->createdAt($date)->bySource($sourcesIds)->byCategory($category->id)->column();
                            $clicks = ArticleClick::find()->where([
                                'date' => $date->toDateString(),
                                'article_id' => $articlesIds,
                                'platform' => $platform,
                            ])->count();

                            $views = ArticleView::find()->where([
                                'date' => $date->toDateString(),
                                'article_id' => $articlesIds,
                                'platform' => $platform,
                            ])->count();

                            if ($clicks || $views) {
                                $batch[] = [
                                    'date' => $date->toDateString(),
                                    'category_id' => $category->id,
                                    'country' => $country->code,
                                    'articles_language' => $language->code ?? null,
                                    'platform' => $platform,
                                    'clicks' => $clicks,
                                    'views' => $views
                                ];
                            }
                        }
                    }
                }

                if (count($batch)) {
                    $sql = \Yii::$app->db->createCommand()->batchInsertIgnoreFromArray(HistoricalStatistics::tableName(), $batch)->getRawSql();
                    \Yii::$app->db->createCommand($sql . ' ON DUPLICATE KEY UPDATE
                    clicks = VALUES(clicks),
                    views = VALUES(views)
                ')->execute();
                }

                if (count($batchUsers)) {
                    $sql = \Yii::$app->db->createCommand()->batchInsertIgnoreFromArray(HistoricalUsersStatistics::tableName(), $batchUsers)->getRawSql();
                    \Yii::$app->db->createCommand($sql . ' ON DUPLICATE KEY UPDATE
                    users_amount = VALUES(users_amount)
                ')->execute();
                }
            }
        }
    }

    public function generatePushNotifications(?CarbonImmutable $dateStart = null, ?CarbonImmutable $dateEnd = null)
    {
        if (is_null($dateStart)) {
            $dateStart = CarbonImmutable::today('UTC');
        }

        if (is_null($dateEnd)) {
            $dateEnd = $dateStart->endOfDay();
        }

        $period = CarbonPeriod::create($dateStart, $dateEnd);

        foreach ($period as $date) {
            $batch = [];
            $articlesIds = PushNotification::find()->select(new Expression('DISTINCT article_id'))->where([
                'AND',
                ['>=', 'created_at', $date->startOfDay()->toDateTimeString()],
                ['<=', 'created_at', $date->endOfDay()->toDateTimeString()],
            ])->column();

            $articles = Article::find()->where(['id' => $articlesIds])->all();
            foreach ([App::PLATFORM_WEB, App::PLATFORM_IOS, App::PLATFORM_ANDROID] as $platform) {
                foreach ($articles as $article) {
                    $amounts = PushNotification::find()
                        ->where(['article_id' => $article->id, 'platform' => $platform])
                        ->select([
                            'MIN(created_at) as send_date',
                            'IFNULL(SUM(sent), 0) as sent',
                            'IFNULL(SUM(clicked), 0) as clicked',
                            'IFNULL(SUM(viewed), 0) as viewed'
                        ])->asArray()->one();

                    if (!$amounts['send_date']) {
                        continue;
                    }

                    $batch[] = [
                        'article_id' => $article->id,
                        'created_at' => $amounts['send_date'],
                        'date' => $date->toDateString(),
                        'country' => $article->source->country,
                        'articles_language' => $article->source->language,
                        'platform' => $platform,
                        'sent_amount' => $amounts['sent'],
                        'clicked_amount' => $amounts['clicked'],
                        'viewed_amount' => $amounts['viewed']
                    ];
                }
            }
            if (count($batch)) {
                $sql = \Yii::$app->db->createCommand()->batchInsertIgnoreFromArray(HistoricalPushNotifications::tableName(), $batch)->getRawSql();
                \Yii::$app->db->createCommand($sql . ' ON DUPLICATE KEY UPDATE
                    sent_amount = VALUES(sent_amount),
                    clicked_amount = VALUES(clicked_amount),
                    viewed_amount = VALUES(viewed_amount)
                ')->execute();
            }

        }
    }
}