<?php namespace common\services;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use common\models\aggregate\ArticlesStatistics;
use common\models\Article;
use common\models\Country;
use common\models\Source;

class PosterService
{
    private $queueManager;

    public function __construct(QueueManager $queueManager)
    {
        $this->queueManager = $queueManager;
    }

    public function postArticlesIfNeeded($country, $language = null)
    {
        $minClicksCount = \Yii::$app->settings->get('SettingsTelegramForm', 'minClicksCount', 10);
        $minCtr = \Yii::$app->settings->get('SettingsTelegramForm', 'minCtr', 15);
        $newArticleTimeLimit = \Yii::$app->settings->get('SettingsTelegramForm', 'newArticleTimeLimit', 30);

        $country = Country::findByCode($country);

        \Yii::$app->db->createCommand("SET time_zone='" . $country->timezone . "';")->execute();

        $sourcesIds = Source::find()
            ->select('sources.id')
            ->byCountry($country->code)
            ->byLanguage($language)
            ->andWhere([
                'telegram' => 1
            ])
            ->column();

        $to = CarbonImmutable::now($country->timezone);
        $from = $to->subMinutes($newArticleTimeLimit);

        if ($from <= ($deployDate = Carbon::parse('2022-04-07 22:50', $country->timezone))) {
            $from = $deployDate;
        }

        $articlesIds = Article::find()
            ->select('id')
            ->createdAt($from, $to)
            ->bySource($sourcesIds)
            ->column();

        $articlesIdsToSend = ArticlesStatistics::find()
            ->select('article_id')
            ->where([
                'AND',
                ['IN', 'article_id', $articlesIds],
                ['>=', 'clicks_common', $minClicksCount],
                ['>=', 'CTR_common', $minCtr],
                ['=', 'posted_to_telegram', 0]
            ])
            ->column();

        $articlesToSend = Article::find()
            ->where([
                'id' => $articlesIdsToSend
            ])
            ->all();

        foreach ($articlesToSend as $article) {
            if (ArticlesStatistics::updateAll([
                'posted_to_telegram' => 1
            ], [
                'article_id' => $article->id
            ])) {
                $this->queueManager->createArticlePostingJob($article);
            }
        }
    }
}