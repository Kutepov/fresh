<?php namespace common\services;

use backend\models\forms\SettingsForm;
use backend\models\search\statistics\CommonSearch;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use common\components\caching\Cache;
use common\models\aggregate\ArticlesStatistics;
use common\models\ArticleShare;
use common\models\Comment;
use common\models\Country;
use common\models\pivot\ArticleRating;
use common\models\Source;
use common\models\statistics\ArticleClick;
use common\models\statistics\ArticleClickTemporary;
use common\models\statistics\ArticleView;
use common\models\statistics\ArticleViewTemporary;
use common\models\Article;
use yii\db\Expression;
use yii\db\Query;
use yii\helpers\ArrayHelper;
use yii\redis\ActiveRecord;

class StatisticsService
{
    const BATCH_SIZE = 1000;

    private $transactionManager;
    private $poster;
    private $pusher;
    private $forker;

    public function __construct(DbManager $transactionManager, PosterService $poster, PusherService $pusher, Forker $forker)
    {
        $this->poster = $poster;
        $this->pusher = $pusher;
        $this->forker = $forker;
        $this->transactionManager = $transactionManager;
    }

    public function clearTemporaryRecoreds()
    {
        ArticleClickTemporary::deleteAll();
        ArticleViewTemporary::deleteAll();
    }

    public function getTemporaryClicksCount($country = null): int
    {
        return ArticleClickTemporary::find()->andFilterWhere(['country' => $country])->count();
    }

    public function getTemporaryViewsCount($country = null): int
    {
        return ArticleViewTemporary::find()->andFilterWhere(['country' => $country])->count();
    }

    public function storeClicksStatistics($country = null, $batchSize = self::BATCH_SIZE): void
    {
        $this->moveTemporaryStatistics(
            ArticleClickTemporary::class,
            ArticleClick::class,
            $country,
            $batchSize
        );
    }

    public function storeViewsStatistics($country = null, $batchSize = self::BATCH_SIZE): void
    {
        $this->moveTemporaryStatistics(
            ArticleViewTemporary::class,
            ArticleView::class,
            $country,
            $batchSize
        );
    }

    private function getMetricsAmount($array, $articleId = null, $widgets = []): int
    {
        $array = array_filter($array, static function ($item) use ($widgets, $articleId) {
            if ($widgets && !in_array($item['widget'], $widgets, true)) {
                return false;
            }

            if ($articleId && $item['article_id'] !== $articleId) {
                return false;
            }

            return true;
        });

        return array_sum(array_map(static function ($item) {
            return $item['amount'];
        }, $array));
    }

    private function getCtrValue($clicks, $views, $round = true): ?float
    {
        if (!$views) {
            return null;
        }

        $ctr = $clicks / $views * 100;

        if ($round) {
            return round($ctr) ?: null;
        }

        return $ctr ?: null;
    }

    private function getModifiedCtrValue($clicks, $views, $article, CarbonInterface $today, $country): ?float
    {
        $ctrUpForRatings = SettingsForm::get('topCtrUpdateForRating', $country);
        $ctrUpForComments = SettingsForm::get('topCtrUpdateForComment', $country);
        $ctrUpForShares = SettingsForm::get('topCtrUpdateForSharing', $country);
        $ctrDecreaseStartHour = SettingsForm::get('ctrDecreaseStartHour', $country);
        $ctrDecreasePercent = SettingsForm::get('ctrDecreasePercent', $country);
        $ctrDecreaseYesterdayPercent = SettingsForm::get('ctrDecreaseYesterdayPercent', $country);

        $articleCreatedAt = CarbonImmutable::parse($article['created_at'], $today->timezone);

        $ctr = $this->getCtrValue($clicks, $views, false) ?: 0;

        if (!$ctr && ($articleCreatedAt->diffInHours() >= $ctrDecreaseStartHour || $articleCreatedAt < $today)) {
            return null;
        }

        $ctrUpForCommentsValue = 0;
        $ctrUpForRatingsValue = 0;
        $ctrUpForSharesValue = 0;

        if ($ctrUpForComments && $article['comments_count']) {
            $ctrUpForCommentsValue = ($ctr / 100 * ($ctrUpForComments * $article['comments_count']));
        }

        if ($ctrUpForRatings && $article['ratings_count']) {
            $ctrUpForRatingsValue = ($ctr / 100 * ($ctrUpForRatings * $article['ratings_count']));
        }

        if ($ctrUpForShares && $article['shares_count']) {
            $ctrUpForSharesValue = ($ctr / 100 * ($ctrUpForShares * $article['shares_count']));
        }

        $modifiedCtr = $ctr + $ctrUpForCommentsValue + $ctrUpForRatingsValue + $ctrUpForSharesValue;

        if ($articleCreatedAt < $today) {
            $modifiedCtr -= $modifiedCtr / 100 * $ctrDecreaseYesterdayPercent * $today->diffInHours();
        }
        else {
            if ($articleCreatedAt->diffInHours() > $ctrDecreaseStartHour) {
                $modifiedCtr -= $modifiedCtr / 100 * ($ctrDecreasePercent * ($articleCreatedAt->diffInHours() - $ctrDecreaseStartHour));
            }
        }

        return round($modifiedCtr);
    }

    private function aggreateStatisticsForCountry(CarbonImmutable $date, $timezone, $country, $language = null, $onlyClicks = false)
    {
        $ctrPeriod = SettingsForm::get('ctrPeriod', $country);
        $ctrDecreaseStartHour = SettingsForm::get('ctrDecreaseStartHour', $country);
        $ctrDecreasePercent = SettingsForm::get('ctrDecreasePercent', $country);
        $minClicksThreshold = SettingsForm::get('minClicksThreshold', $country);

        $endDate = $date->setTimezone($timezone);
        $startDate = $date->setTimezone($timezone)->subHours(24);
        $today = $endDate->startOfDay();

        $ctrPeriodEnd = $date->setTimezone($timezone)->toDateTimeString();
        $ctrPeriodStart = $date->setTimezone($timezone)->subHours($ctrPeriod)->toDateTimeString();

        echo 'START ' . $country . '-' . $language . PHP_EOL;
        /** Пересчет статистики */

        \Yii::$app->db->createCommand("SET time_zone='" . $timezone . "';")->execute();

        $sourcesIds = Source::find()
            ->select('id')
            ->where([
                'country' => $country,
                'language' => $language
            ])
            ->column();

        $articles = Article::find()
            ->select(['id', 'category_id', 'created_at', 'comments_count', 'ratings_count', 'shares_count'])
            ->where([
                'AND',
                ['IN', 'source_id', $sourcesIds],
                ['BETWEEN', 'created_at', $startDate->toDateTimeString(), $endDate->toDateTimeString()]
            ])
            ->asArray()
            ->all();

        $newRecords = [];

        $clicks = (new Query())
            ->from(ArticleClick::tableName())
            ->select(['COUNT(*) as amount', 'widget', 'article_id'])
            ->groupBy(['widget', 'article_id'])
            ->where([
                'AND',
                ['IN', 'widget', ['my-feed', 'category', 'similar-articles', 'my-feed-top']],
                ['BETWEEN', 'created_at', $ctrPeriodStart, $ctrPeriodEnd],
                ['IN', 'article_id', ArrayHelper::getColumn($articles, 'id')]
            ])
            ->all();

        $commonClicks = (new Query())
            ->from(ArticleClick::tableName())
            ->select(['COUNT(*) as amount', 'widget', 'article_id'])
            ->groupBy(['widget', 'article_id'])
            ->where([
                'AND',
                ['IN', 'widget', ['my-feed', 'category', 'similar-articles', 'my-feed-top']],
                ['BETWEEN', 'created_at', $startDate->toDateTimeString(), $endDate->toDateTimeString()],
                ['IN', 'article_id', ArrayHelper::getColumn($articles, 'id')]
            ])
            ->all();

        $views = (new Query())
            ->from(ArticleView::tableName())
            ->select(['COUNT(*) as amount', 'widget', 'article_id'])
            ->groupBy(['widget', 'article_id'])
            ->where([
                'AND',
                ['IN', 'widget', ['my-feed', 'category', 'similar-articles', 'my-feed-top']],
                ['BETWEEN', 'created_at', $ctrPeriodStart, $ctrPeriodEnd],
                ['IN', 'article_id', ArrayHelper::getColumn($articles, 'id')]
            ])
            ->all();

        $commonViews = (new Query())
            ->from(ArticleView::tableName())
            ->select(['COUNT(*) as amount', 'widget', 'article_id'])
            ->groupBy(['widget', 'article_id'])
            ->where([
                'AND',
                ['IN', 'widget', ['my-feed', 'category', 'similar-articles', 'my-feed-top']],
                ['BETWEEN', 'created_at', $startDate->toDateTimeString(), $endDate->toDateTimeString()],
                ['IN', 'article_id', ArrayHelper::getColumn($articles, 'id')]
            ])
            ->all();

        $articlesIds = ArrayHelper::getColumn($articles, 'id');
        foreach ($articles as $article) {
            $clicked = $this->getMetricsAmount($clicks, $article['id'], ['my-feed', 'category', 'similar-articles']);
            $clickedTop = $this->getMetricsAmount($clicks, $article['id'], ['my-feed-top']);
            $showed = $this->getMetricsAmount($views, $article['id'], ['my-feed', 'category', 'similar-articles']);
            $showedTop = $this->getMetricsAmount($views, $article['id'], ['my-feed-top']);

            $clicksCommon = $this->getMetricsAmount($commonClicks, $article['id']);
            $clicksFeed = $this->getMetricsAmount($commonClicks, $article['id'], ['my-feed', 'category']);
            $clicksTop = $this->getMetricsAmount($commonClicks, $article['id'], ['my-feed-top']);
            $clicksSimilarArticles = $this->getMetricsAmount($commonClicks, $article['id'], ['similar-articles']);

            $viewsCommon = $this->getMetricsAmount($commonViews, $article['id']);
            $viewsFeed = $this->getMetricsAmount($commonViews, $article['id'], ['my-feed', 'category']);
            $viewsTop = $this->getMetricsAmount($commonViews, $article['id'], ['my-feed-top']);
            $viewsSimilarArticles = $this->getMetricsAmount($commonViews, $article['id'], ['similar-articles']);


            $articleCreatedAt = CarbonImmutable::parse($article['created_at'], new \DateTimeZone($timezone));

            $newRecords[] = [
                'article_id' => $article['id'],
                'article_category_id' => $article['category_id'],
                'article_created_at' => $article['created_at'],
                'created_at' => $date->toDateString(),
                'CTR' => $this->getCtrValue($clicked, $showed),
                'CTR_top' => $this->getCtrValue($clickedTop, $showedTop),
                'CTR_common' => $this->getCtrValue($clicked + $clickedTop, $showed + $showedTop) ?: 0,
                'CTR_modified' => $this->getModifiedCtrValue($clicked, $showed, $article, $today, $country),
                'CTR_top_modified' => $this->getModifiedCtrValue($clickedTop, $showedTop, $article, $today, $country),
                'CTR_common_modified' => $this->getModifiedCtrValue($clicked + $clickedTop, $showed + $showedTop, $article, $today, $country),
                'clicked' => $clicked,
                'showed' => $showed,
                'hours_diff' => $articleCreatedAt->diffInHours(),
                'CTR_percent_to_decrease' => $articleCreatedAt->diffInHours() > $ctrDecreaseStartHour ? ($ctrDecreasePercent * ($articleCreatedAt->diffInHours() - $ctrDecreaseStartHour)) : 0,
                'clicked_top' => $clickedTop,
                'showed_top' => $showedTop,
                'comments_count' => $article['comments_count'],
                'ratings_count' => $article['ratings_count'],
                'shares_count' => $article['shares_count'],
                'clicks_common' => $clicksCommon ?: null,
                'clicks_feed' => $clicksFeed ?: null,
                'clicks_top' => $clicksTop ?: null,
                'clicks_similar_articles' => $clicksSimilarArticles ?: null,
                'views_common' => $viewsCommon ?: null,
                'views_feed' => $viewsFeed ?: null,
                'views_top' => $viewsTop ?: null,
                'views_similar_articles' => $viewsSimilarArticles ?: null
            ];
        }

        if (count($newRecords)) {
            foreach (array_chunk($newRecords, 1000) as $chunk) {
                $sql = \Yii::$app->db->createCommand()->batchInsertIgnoreFromArray(ArticlesStatistics::tableName(), $chunk)->getRawSql();
                \Yii::$app->db->createCommand($sql . ' ON DUPLICATE KEY UPDATE
                clicked = VALUES(clicked),
                showed = VALUES(showed),
                ctr = VALUES(ctr),
                clicked_top = VALUES(clicked_top),
                showed_top = VALUES(showed_top),
                ctr_top = VALUES(ctr_top),
                ctr_common = VALUES(ctr_common),
                ctr_common_modified = VALUES(ctr_common_modified),
                hours_diff = VALUES(hours_diff),
                CTR_percent_to_decrease = VALUES(CTR_percent_to_decrease),
                CTR_top_modified = VALUES(CTR_top_modified),
                ctr_modified = VALUES(ctr_modified),
                comments_count = VALUES(comments_count),
                ratings_count = VALUES(ratings_count),
                shares_count = VALUES(shares_count),
                clicks_common = VALUES(clicks_common),
                clicks_feed = VALUES(clicks_feed),
                clicks_top = VALUES(clicks_top),
                clicks_similar_articles = VALUES(clicks_similar_articles),
                views_common = VALUES(views_common),
                views_feed = VALUES(views_feed),
                views_top = VALUES(views_top),
                views_similar_articles = VALUES(views_similar_articles)')->execute();
            }
        }

        echo 'END ' . $country . PHP_EOL;
        /** TODO: Fix this */
        if ($country === 'UA') {
            $this->poster->postArticlesIfNeeded($country, $language);
        }

        $this->pusher->pushArticlesIfNeeded($country, $language);

        /** Запись мест новостей в топе */
        $newTopArticles = ArticlesStatistics::find()
            ->where([
                'article_id' => $articlesIds
            ])
            ->andWhere([
                'AND',
                ['>=', new Expression('clicked_top + clicked'), $minClicksThreshold]
            ])
            ->orderByCommonCTR()
            ->newestFirst()
            ->all();

        $exceptTopArticlesIds = [];

        foreach ($newTopArticles as $i => $topArticle) {
            $topArticle->updateAttributes([
                'top_position' => $i + 1,
                'accelerated_at' => null
            ]);
            $exceptTopArticlesIds[] = $topArticle->article_id;
        }

        $lastTopPosition = count($newTopArticles) + 1;

        /** Запись мест новостей в топе */
        $newTopArticles = ArticlesStatistics::find()
            ->where([
                'article_id' => $articlesIds
            ])
            ->andWhere([
                'AND',
                ['<', new Expression('clicked_top + clicked'), $minClicksThreshold],
                ['NOT IN', 'article_id', $exceptTopArticlesIds]
            ])
            ->addOrderBy(new Expression('clicked_top + clicked DESC'))
            ->orderByCommonCTR()
            ->newestFirst()
            ->all();


        if (count($newTopArticles)) {
            foreach ($newTopArticles as $i => $topArticle) {
                $topArticle->updateAttributes([
                    'top_position' => $lastTopPosition + $i,
                    'accelerated_at' => null
                ]);
            }
        }

        $articlesIds = Article::find()
            ->select('articles.id')
            ->createdAt($startDate->subHours(24), $endDate->subHours(24))
            ->bySource(
                null,
                $country,
                $language
            )
            ->column();

        ArticlesStatistics::updateAll([
            'top_position' => null
        ], [
            'article_id' => $articlesIds
        ]);
    }

    /**
     * TODO: refactor
     * @param Carbon $date
     * @throws \yii\db\Exception
     */
    public function aggregateStatistics(CarbonImmutable $date, $onlyClicks = false, $countryCode = null, $force = false)
    {
        $countries = Country::find()
            ->orderBy(new Expression("FIELD(code, 'by', 'pl', 'ru', 'ua') DESC"))
            ->addOrderBy([
                'top_calculated_at' => SORT_ASC
            ]);

        foreach ($countries->all() as $country) {
            $topCalculationPeriod = SettingsForm::get('topCalculationPeriod', $country->code);

            if ($countryCode && $country->code !== $countryCode) {
                continue;
            }
            $country->refresh();

            $this->storeClicksStatistics();
            if (!$onlyClicks) {
                $this->storeViewsStatistics();
            }

            if (!$force) {
                if (!(!$country->top_locked || !$country->top_calculated_at || $country->top_calculated_at <= Carbon::now()->subMinutes($topCalculationPeriod * 2))) {
                    continue;
                }

                if (!$onlyClicks) {
                    if (!(!$country->top_calculated_at || $country->top_calculated_at <= Carbon::now()->subMinutes($topCalculationPeriod)->toDateTimeString())) {
                        continue;
                    }
                }
            }

            if (!$country->top_locked || $country->top_calculated_at->diffInMinutes() >= 10) {
                if (!$onlyClicks) {
                    $country->lockForTopCalculation();
                }

                foreach ($country->articlesLanguages ?: [null] as $language) {
                    if (!defined('CONSOLE_DEBUG')) {
                        $this->aggreateStatisticsForCountry($date, $country->timezone, $country->code, $language->code ?? null, $onlyClicks);
                    }
                }

                if (!$onlyClicks) {
                    \Yii::$app->db->createCommand("SET time_zone='UTC';")->execute();
                    $country->unlockForTopCalculation();
                    $country->updateAttributes([
                        'top_calculated_at' => Carbon::now()
                    ]);
                    Cache::clearByTag(Cache::TAG_COUNTRY);
                }
            }
        }

        if (!$onlyClicks) {
            \Yii::$app->cache->set('topCalculatedTime', time());
        }
    }

    /**
     * @param string|ActiveRecord $fromModelClass
     * @param string|\yii\db\ActiveRecord $toModelClass
     * @param int $batchSize
     */
    private function moveTemporaryStatistics(string $fromModelClass, string $toModelClass, $country = null, $batchSize = self::BATCH_SIZE): void
    {
        do {
            $models = $fromModelClass::find()
                ->andFilterWhere([
                    'country' => $country
                ])
                ->limit($batchSize)
                ->asArray()
                ->all();

            if (count($models)) {
                $this->transactionManager->wrap(function () use (&$toModelClass, &$fromModelClass, $models) {
                    if (defined('CONSOLE_DEBUG')) {
                        print_r($models);
                    }
                    $models = array_map(static function (array $model) use ($fromModelClass) {

                        $needKeys = (new $fromModelClass())->attributes();
                        sort($needKeys);
                        $currentKeys = array_keys($model);
                        /** TODO: remove tomorrow */
                        if (!in_array('preview_type', $currentKeys, true)) {
                            $currentKeys[] = 'preview_type';
                            $model['preview_type'] = null;
                        }
                        sort($currentKeys);

                        if (defined('CONSOLE_DEBUG')) {
                            print_r($currentKeys);
                        }
                        if ($currentKeys !== $needKeys) {
                            return null;
                        }

                        unset($model['id']);
                        $model['date'] = substr($model['created_at'], 0, 10);

                        ksort($model);

                        return $model;

                    }, $models);

                    if (defined('CONSOLE_DEBUG')) {
                        print_r($models);
                    }
                    $models = array_filter($models);

                    $articlesIds = array_unique(array_map(static function ($m) {
                        return $m['article_id'];
                    }, $models));

                    $articlesCategories = Article::find()
                        ->indexBy('id')
                        ->select('category_id')
                        ->where([
                            'id' => $articlesIds
                        ])
                        ->column(null, false);

                    $models = array_map(static function ($m) use ($articlesCategories) {
                        $m['category_id'] = $articlesCategories[$m['article_id']];
                        return $m;
                    }, $models);

                    if (count($models)) {
                        \Yii::$app->db
                            ->createCommand()
                            ->batchInsertIgnoreFromArray($toModelClass::tableName(), $models)
                            ->execute();
                    }
                });

                $fromModelClass::deleteAll([
                    'id' => ArrayHelper::getColumn($models, 'id')
                ]);
            }
        } while (count($models) >= $batchSize);
    }

    public function dailyCache($cachePeriod = 600)
    {
        $countries = Country::find()->all();

        foreach (ArrayHelper::merge([null], $countries) as $country) {
            $params = ['CommonSearch' => ['country_id' => $country->code ?? null]];
            foreach (ArrayHelper::merge([null], $country->articlesLanguages ?: []) as $language) {
                $params['CommonSearch']['language'] = $language->code ?? null;
                foreach ([null, 'ios', 'android'] as $platform) {
                    $params['CommonSearch']['platform'] = $platform;
                    $this->forker->invoke(function () use ($params, $cachePeriod) {
                        $searchModel = new CommonSearch();
                        $dataProvider = $searchModel->search($params, $cachePeriod, true);

                        $dataProvider->prepare();

                        $params = $dataProvider->query->createCommand()->params;

                        ksort($params, SORT_STRING);

                        \Yii::$app->cache->set([
                            \Yii::$app->db->commandMap['mysql'],
                            'fetchAll',
                            null,
                            \Yii::$app->db->dsn,
                            \Yii::$app->db->username,
                            $dataProvider->query->createCommand()->getSql(),
                            json_encode($params)
                        ], [$dataProvider->models], $cachePeriod);

                    }, 16);
                }
            }
        }

        $this->forker->wait();
    }

    public function generateArticleTopLog($articleId): ?array
    {
        if ($article = Article::findOne($articleId)) {
            if (!$article->source->default) {
                return null;
            }

            $tz = $article->source->timezone;

            $now = CarbonImmutable::now($tz);
            \Yii::$app->db->createCommand("SET TIME_ZONE='" . $tz . "'")->execute();
            $articleCreatedAt = $article->created_at->toImmutable()->setTimezone($tz);

            $startDate = null;

            $endDate = $article->source->countryModel->top_calculated_at->setTimezone($tz)->toImmutable();

            do {
                if ($startDate) {
                    $startDate = $startDate->subMinutes(15);
                }
                else {
                    $startDate = $endDate->subMinutes(15);
                }
            } while ($startDate->diffInMinutes($articleCreatedAt) > 15);


            $ctrUpForRatings = SettingsForm::get('topCtrUpdateForRating', $article->source->country);
            $ctrUpForComments = SettingsForm::get('topCtrUpdateForComment', $article->source->country);
            $ctrUpForShares = SettingsForm::get('topCtrUpdateForSharing', $article->source->country);
            $ctrDecreaseStartHour = SettingsForm::get('ctrDecreaseStartHour', $article->source->country);
            $ctrDecreasePercent = SettingsForm::get('ctrDecreasePercent', $article->source->country);
            $ctrDecreaseYesterdayPercent = SettingsForm::get('ctrDecreaseYesterdayPercent', $article->source->country);

            if ($endDate->diffInDays($startDate) > 1) {
                $endDate = $startDate->addDay();
            }

            $result = [['Время пересчета топа', 'CTR за 2 часа', 'CTR за все время', '2 часа', 'Все время']];
            if ($startDate->greaterThan($now)) {
                return null;
            }

            do {
                $rating = ArticleRating::find()->where([
                    'AND',
                    ['=', 'article_id', $article->id],
                    ['<=', 'created_at', $startDate->toDateTimeString()]
                ])->count();

                $shares = ArticleShare::find()->where([
                    'AND',
                    ['=', 'article_id', $article->id],
                    ['<=', 'created_at', $startDate->toDateTimeString()]
                ])->count();

                $comments = Comment::find()->where([
                    'AND',
                    ['=', 'article_id', $article->id],
                    ['<=', 'created_at', $startDate->toDateTimeString()]
                ])->count();

                $clicksTotal = ArticleClick::find()->where([
                    'AND',
                    ['IN', 'widget', ['my-feed', 'category', 'similar-articles', 'my-feed-top']],
                    ['=', 'article_id', $article->id],
                    ['<=', 'created_at', $startDate->toDateTimeString()]
                ])->count();

                $viewsTotal = ArticleView::find()->where([
                    'AND',
                    ['IN', 'widget', ['my-feed', 'category', 'similar-articles', 'my-feed-top']],
                    ['=', 'article_id', $article->id],
                    ['<=', 'created_at', $startDate->toDateTimeString()]
                ])->count();

                $clicks = ArticleClick::find()->where([
                    'AND',
                    ['=', 'article_id', $article->id],
                    ['IN', 'widget', ['my-feed', 'category', 'similar-articles', 'my-feed-top']],
                    ['>=', 'created_at', $startDate->subHours(2)->toDateTimeString()],
                    ['<=', 'created_at', $startDate->toDateTimeString()]
                ])->count();

                $views = ArticleView::find()->where([
                    'AND',
                    ['=', 'article_id', $article->id],
                    ['IN', 'widget', ['my-feed', 'category', 'similar-articles', 'my-feed-top']],
                    ['>=', 'created_at', $startDate->subHours(2)->toDateTimeString()],
                    ['<=', 'created_at', $startDate->toDateTimeString()]
                ])->count();

                $clicksSimilar = ArticleClick::find()->where([
                    'AND',
                    ['=', 'article_id', $article->id],
                    ['IN', 'widget', ['similar-articles']],
                    ['>=', 'created_at', $startDate->subHours(2)->toDateTimeString()],
                    ['<=', 'created_at', $startDate->toDateTimeString()]
                ])->count();

                $viewsSimilar = ArticleView::find()->where([
                    'AND',
                    ['=', 'article_id', $article->id],
                    ['IN', 'widget', ['similar-articles']],
                    ['>=', 'created_at', $startDate->subHours(2)->toDateTimeString()],
                    ['<=', 'created_at', $startDate->toDateTimeString()]
                ])->count();

                if ($views && $viewsTotal) {
                    $ctrCommon = $ctrCommonOrig = ($clicksTotal / $viewsTotal) * 100;
                    $ctrCommon += ($ctrCommonOrig / 100 * ($ctrUpForRatings * $rating));
                    $ctrCommon += ($ctrCommonOrig / 100 * ($ctrUpForShares * $shares));
                    $ctrCommon += ($ctrCommonOrig / 100 * ($ctrUpForComments * $comments));


                    $ctr = $ctrOrig = ($clicks / $views) * 100;
                    $ctr += ($ctrOrig / 100 * ($ctrUpForRatings * $rating));
                    $ctr += ($ctrOrig / 100 * ($ctrUpForShares * $shares));
                    $ctr += ($ctrOrig / 100 * ($ctrUpForComments * $comments));

                    if (!$articleCreatedAt->isSameDay($startDate)) {
                        $ctrCommon -= $ctrCommon / 100 * $ctrDecreaseYesterdayPercent * $startDate->startOfDay()->diffInHours($startDate);
                        $ctr -= $ctr / 100 * $ctrDecreaseYesterdayPercent * $startDate->startOfDay()->diffInHours($startDate);
                    }
                    else if ($articleCreatedAt->diffInHours($startDate) > $ctrDecreaseStartHour) {
                        $ctrCommon -= $ctrCommon / 100 * ($ctrDecreasePercent * ($articleCreatedAt->diffInHours($startDate) - $ctrDecreaseStartHour));
                        $ctr -= $ctr / 100 * ($ctrDecreasePercent * ($articleCreatedAt->diffInHours($startDate) - $ctrDecreaseStartHour));
                    }

                    $result[] = [
                        $startDate->format('d.m.Y H:i'),
                        round($ctr) . '%',
                        round($ctrCommon) . '%',
                        'клики: ' . $clicks . ', показы: ' . $views,
                        'клики: ' . $clicksTotal . ', показы: ' . $viewsTotal
                    ];
                }

                $startDate = $startDate->addMinutes(15);

            } while ($startDate->lessThanOrEqualTo($endDate));

            return $result;
        }

        return null;
    }
}