<?php namespace common\services;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use common\models\aggregate\ArticlesStatistics;
use common\models\Article;
use common\models\Country;
use common\models\Source;

class PusherService
{
    private $queueManager;

    public function __construct(QueueManager $queueManager)
    {
        $this->queueManager = $queueManager;
    }

    public function pushArticlesIfNeeded($country, $language = null)
    {
        $key = 'pushes-' . strtoupper($country);
        $minClicksCount = \Yii::$app->settings->get($key, 'minClicksCount');
        $enabled = \Yii::$app->settings->get($key, 'enabled');
        $minCtr = \Yii::$app->settings->get($key, 'minCtr');
        $newArticleTimeLimit = \Yii::$app->settings->get($key, 'newArticleTimeLimit');
        $periodBetweenPushes = \Yii::$app->settings->get($key, 'periodBetweenPushes', 1) * 60;

        if (!$enabled || !$minClicksCount || !$minCtr || !$newArticleTimeLimit) {
            return;
        }

        $country = Country::findByCode($country);

        \Yii::$app->db->createCommand("SET time_zone='" . $country->timezone . "';")->execute();

        $sourcesIds = Source::find()
            ->select('sources.id')
            ->byCountry($country->code)
            ->byLanguage($language)
            ->andWhere([
                'push_notifications' => 1
            ])
            ->column();

        if (count($sourcesIds)) {
            $to = CarbonImmutable::now($country->timezone);
            $from = $to->subMinutes($newArticleTimeLimit);

            if ($from <= ($deployDate = Carbon::parse('2022-04-20 16:30', $country->timezone))) {
                $from = $deployDate;
            }

            $articlesIds = Article::find()
                ->select('id')
                ->createdAt($from, $to)
                ->bySource($sourcesIds)
                ->column();

            $lastPushedArticleTime = ArticlesStatistics::find()
                ->select('MAX(pushed_at)')
                ->where([
                    'IN', 'article_id', $articlesIds
                ])
                ->scalar();

            if ($lastPushedArticleTime + $periodBetweenPushes < time()) {
                $articlesIdsToSend = ArticlesStatistics::find()
                    ->select('article_id')
                    ->where([
                        'AND',
                        ['IN', 'article_id', $articlesIds],
                        ['>=', 'clicks_common', $minClicksCount],
                        ['>=', 'CTR_common', $minCtr],
                        ['IS', 'pushed_at', null]
                    ])
                    ->mostTopFirst()
                    ->limit(1)
                    ->column();

                $article = Article::find()
                    ->where([
                        'id' => $articlesIdsToSend
                    ])
                    ->one();

                if ($article) {
                    if (ArticlesStatistics::updateAll([
                        'pushed_at' => time()
                    ], [
                        'article_id' => $article->id
                    ])) {
                        $this->queueManager->createTopArticlePushNotificationJob($article);
                    }
                }
            }
        }
    }
}