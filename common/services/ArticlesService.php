<?php namespace common\services;

use api\models\search\ArticlesSearch;
use api\models\search\ArticlesGroupedByCategorySearch;
use api\models\search\SameArticlesSearch;
use api\models\search\SimilarArticlesSearch;
use api\models\search\TopArticlesSearch;
use Assert\Assertion;
use backend\models\forms\SettingsForm;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use common\components\helpers\Api;
use common\contracts\Logger;
use common\models\aggregate\ArticlesStatistics;
use common\models\App;
use common\models\Article;
use common\models\ArticleShare;
use common\models\Category;
use common\models\Country;
use common\models\pivot\ArticleRating;
use common\models\User;
use yii\base\UserException;
use yii\db\ActiveQuery;
use yii\db\Expression;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;

class ArticlesService
{
    /** @var \common\services\ArticlesIndexer */
    private $indexer;
    /** @var \common\contracts\Logger */
    private $logger;

    public function __construct(ArticlesIndexer $indexer, Logger $logger)
    {
        $this->logger = $logger;
        $this->indexer = $indexer;
    }

    public function getEncodedBody(Article $article): string
    {
        return Json::encode($article->body);
    }

    public function getEncodedBodies(array $articles): array
    {
        return array_map(function (Article $article) {
            return [
                'id' => $article->id,
                'body' => $this->getEncodedBody($article)
            ];
        }, $articles);
    }

    public function getByIds(ArticlesSearch $searchModel): array
    {
        if (!count($searchModel->ids)) {
            return [];
        }
        $articles = Article::find()
            ->byIds($searchModel->ids)
            ->all(null, false);

        return $this->addSameArticlesAmount(
            $articles,
            $searchModel->source,
            $searchModel->sourceUrl,
            $searchModel->country,
            $searchModel->articlesLanguage,
            $searchModel->skipBanned
        );
    }

    public function getById(string $id): ?Article
    {
        return Article::find()->where(['id' => $id])->one(null, false);
    }

    public function getBySlug(string $slug): ?Article
    {
        return Article::find()->where(['slug' => $slug])->one(null, false);
    }

    /**
     * Поиск и фильтрация новостей
     * @param ArticlesSearch $searchModel
     * @return Article[]
     */
    public function search(ArticlesSearch $searchModel): array
    {
        if ($searchModel->scenario == ArticlesSearch::SCENARIO_SEARCH) {
            $articlesIds = $this->indexer->searchArticlesIds($searchModel);
            if (count($articlesIds)) {
                $articles = Article::find()
                    ->byIds($articlesIds)
                    ->newestFirst()
                    ->all();
            } else {
                return [];
            }
        } else {
            $articles = Article::find()
                ->byCategory($searchModel->category)
                ->olderThan($searchModel->createdBefore)
                ->skipBanned($searchModel->skipBanned);

            if (Api::versionLessThan(Api::V_2_20)) {
                $articles
                    ->bySource(
                        $searchModel->source,
                        $searchModel->country,
                        $searchModel->articlesLanguage
                    );
            } elseif (!Api::isRequestFromApp()) {
                $articles->byCountry($searchModel->country);
            } else {
                $articles->bySourceUrl($searchModel->sourceUrl);
            }

            $articles->newestFirst()
                ->offset($searchModel->offset)
                ->limit($searchModel->limit);

            $articles = $articles->all();

            if ($searchModel->widget) {
                foreach ($articles as $article) {
                    $article->scenario = Article::SCENARIO_WIDGET;
                }
            }
        }

        return $this->addSameArticlesAmount(
            $articles,
            $searchModel->source,
            $searchModel->sourceUrl,
            $searchModel->country,
            $searchModel->articlesLanguage,
            $searchModel->skipBanned
        );
    }

    public function getNewArticlesAmount(ArticlesSearch $searchModel): int
    {
        return Article::find()
            ->newerThan($searchModel->createdAfter)
            ->skipBanned($searchModel->skipBanned)
            ->bySourceUrl($searchModel->sourceUrl)
            ->count();
    }

    public function getArticlesCount(ArticlesSearch $searchModel): int
    {
        $articles = Article::find()
            ->byCategory($searchModel->category)
            ->olderThan($searchModel->createdBefore)
            ->skipBanned($searchModel->skipBanned);

        if (!Api::isRequestFromApp() || Api::versionLessThan(Api::V_2_20)) {
            $articles->bySource(
                $searchModel->source,
                $searchModel->country,
                $searchModel->articlesLanguage
            );
        } else {
            $articles->bySourceUrl($searchModel->sourceUrl);
        }

        return $articles->count();
    }

    public function getSameArticlesAmount(SameArticlesSearch $searchModel): array
    {
        $articles = Article::find()
            ->select(['same_article_id', new Expression('COUNT(id) as `count`')]);

        if (!Api::isRequestFromApp() || Api::versionLessThan(Api::V_2_20)) {
            $articles->bySource(
                $searchModel->source,
                $searchModel->country,
                $searchModel->articlesLanguage
            );
        } else {
            $articles->bySourceUrl($searchModel->sourceUrl);
        }

        $articles = $articles->skipBanned($searchModel->skipBanned)
            ->byParentArticlesIds(array_values($searchModel->parentArticlesIds))
            ->groupBy('same_article_id')
            ->asArray()
            ->all(null, false);

        $result = ArrayHelper::map($articles, 'same_article_id', 'count');

        return array_map(static function ($value) {
            return (int)$value;
        }, $result);
    }

    /**
     * Поиск одинаковых новостей из разных источников
     * @param SameArticlesSearch $searchModel
     * @return Article[]
     */
    public function searchSameArticles(SameArticlesSearch $searchModel): array
    {
        $articles = Article::find();

        if (!Api::isRequestFromApp() || Api::versionLessThan(Api::V_2_20)) {
            $articles->bySource(
                $searchModel->source,
                $searchModel->country,
                $searchModel->articlesLanguage
            );
        } else {
            $articles->bySourceUrl($searchModel->sourceUrl);
        }

        return $articles->skipBanned($searchModel->skipBanned)
            ->byParentArticleId($searchModel->parentArticleId)
            ->newestFirst()
            ->all(null, false);
    }

    private function getSimilarArticles(SimilarArticlesSearch $searchModel, CarbonImmutable $dateFrom, CarbonImmutable $dateTo)
    {
        $topArticlesSearchModel = (new TopArticlesSearch())
            ->loadAndValidate([
                'source' => $searchModel->source,
                'country' => $searchModel->country,
                'articlesLanguage' => $searchModel->articlesLanguage,
                'limit' => 3,
                'skipBanned' => $searchModel->skipBanned
            ]);

        $currentTopArticlesIds = ArrayHelper::getColumn(
            $this->getTopArticles($topArticlesSearchModel),
            'id'
        );

        $articlesIdsQuery = Article::find()
            ->select('articles.id')
            ->createdAt($dateFrom, $dateTo)
            ->andWhere(['NOT IN', 'id', $currentTopArticlesIds])
            ->byCategory($searchModel->category);

        if (!Api::isRequestFromApp() || Api::versionLessThan(Api::V_2_20)) {
            $articlesIdsQuery->bySource(
                $searchModel->source,
                $searchModel->country,
                $searchModel->articlesLanguage
            );
        } else {
            $articlesIdsQuery->bySourceUrl($searchModel->sourceUrl);
        }

        $articlesIdsQuery->skipBanned($searchModel->skipBanned)
            ->newestFirst();


        if ($article = $searchModel->getArticle()) {
            $articlesIdsQuery
                ->byCategory($article->category_id)
                ->andWhere(['<>', 'articles.id', $article->id]);
        }

        $allArticlesIds = $articlesIdsQuery->column();

        $articlesIds = $this
            ->getTopArticlesQuery($allArticlesIds, $searchModel->limit)
            ->andWhere(['<>', 'article_id', $article->id])
            ->onlyTop()
            ->column();

        if (count($articlesIds) < $searchModel->limit) {
            $allArticlesIds = array_diff($allArticlesIds, $articlesIds);
            $articlesIds = ArrayHelper::merge($articlesIds, $this
                ->getTopArticlesQuery($allArticlesIds, $searchModel->limit - count($articlesIds))
                ->andWhere(['<>', 'article_id', $article->id])
                ->andWhere(['NOT IN', 'article_id', $articlesIds])
                ->column());
        }

        if (count($articlesIds) < $searchModel->limit) {
            $allArticlesIds = array_diff($allArticlesIds, $articlesIds);
            $articlesIds = array_slice($allArticlesIds, 0, $searchModel->limit);
        }

        return Article::find()
            ->where([
                'id' => $articlesIds
            ])
            ->newestFirst()
            ->all();
    }

    public function searchSimilarArticles(SimilarArticlesSearch $searchModel): array
    {
        $similarArticles = [];

        if (Api::version(Api::V_2_0)) {
            if (Api::isRequestFromApp()) {
                $limit = 10;
                $searchModel->limit = $limit;
            } else {
                $limit = $searchModel->limit;
            }

            $dateTo = CarbonImmutable::now();
            $dateFrom = $dateTo->subHours(24);
            $similarArticles = $this->getSimilarArticles(
                $searchModel,
                $dateFrom,
                $dateTo
            );

            if (count($similarArticles) < $limit) {
                $searchModel->limit = $limit - count($similarArticles);

                $dateTo = CarbonImmutable::now()->subHours(24);
                $dateFrom = $dateTo->subDays(21);
                $similarArticles = ArrayHelper::merge($similarArticles, $this->getSimilarArticles(
                    $searchModel,
                    $dateFrom,
                    $dateTo
                ));
            }

            if (count($similarArticles) === $limit) {
                return $similarArticles;
            }

            $searchModel->limit = $limit - count($similarArticles);
        }

        try {
            $articles = $this->indexer->findSimilarArticles($searchModel);
        } catch (\Exception $e) {
            $this->logger->critical($e, [Logger::ELASTICSEARCH]);
            return [];
        }

        $articles = array_merge($similarArticles, $articles);

        return $this->addSameArticlesAmount(
            $articles,
            $searchModel->source,
            $searchModel->sourceUrl,
            $searchModel->country,
            $searchModel->articlesLanguage,
            $searchModel->skipBanned
        );
    }

    /**
     * Топ новостей по CTR за заданный день
     * @param TopArticlesSearch $searchModel
     * @param Carbon|null $date
     * @return array
     */
    public function getTopArticles(TopArticlesSearch $searchModel): array
    {
        $minClicksThreshold = SettingsForm::get('minClicksThreshold', $searchModel->country);

        $date = CarbonImmutable::now();
        $dateFrom = $date->subHours(24);

        if ($country = Country::findByCode($searchModel->country)) {
            $date = $date->setTimezone($country->timezone);
            $dateFrom = $date->setTimezone($country->timezone)->subHours(24);
        }

        /** Оставляем одно место для "разгонной" новости */
        $topLimit = $searchModel->limit;

        $articlesIds = Article::find()
            ->select('articles.id')
            ->createdAt($dateFrom, $date);
        if (!Api::isRequestFromApp() || Api::versionLessThan(Api::V_2_20)) {
            $articlesIds->bySource(
                $searchModel->source,
                $searchModel->country,
                $searchModel->articlesLanguage
            );
        } else {
            $articlesIds->bySourceUrl($searchModel->sourceUrl);
        }

        $articlesIds = $articlesIds->byCategory($searchModel->category)
            ->skipBanned($searchModel->skipBanned)
            ->column();

        $existsStatisticsIds = $this
            ->getTopArticlesQuery($articlesIds, $topLimit)
            ->onlyTop($minClicksThreshold)
            ->column();

        if (count($existsStatisticsIds) < $topLimit) {
            $existsStatisticsIds = ArrayHelper::merge(
                $existsStatisticsIds,
                $this->getTopArticlesQuery($articlesIds, $topLimit)
                    ->andWhere(['NOT IN', 'article_id', $existsStatisticsIds])
                    ->column()
            );
        }

        $articles = Article::findByIds($existsStatisticsIds);

        if ($searchModel->widget) {
            foreach ($articles as $article) {
                $article->scenario = Article::SCENARIO_WIDGET;
            }
        }

        return $this->addSameArticlesAmount(
            $articles,
            $searchModel->source,
            $searchModel->sourceUrl,
            $searchModel->country,
            $searchModel->articlesLanguage,
            $searchModel->skipBanned
        );
    }

    private function getTopArticlesQuery($articlesIds, $limit): ActiveQuery
    {
        return ArticlesStatistics::find()
            ->select('article_id')
            ->andWhere(['IN', 'article_id', $articlesIds])
            ->mostTopFirst()
            ->limit($limit);
    }

    private function getTopAcceleratedArticleQuery($articlesIds, $existsStatisticsIds): ActiveQuery
    {
        return ArticlesStatistics::find()
            ->select('article_id')
            ->andWhere(['IN', 'article_id', $articlesIds])
            ->andWhere(['NOT IN', 'article_id', $existsStatisticsIds])
            ->acceleratedFirst()
            ->mostTopFirst()
            ->limit(1);
    }

    /**
     * @param ArticlesGroupedByCategorySearch $searchModel
     * @return array
     * @deprecated
     */
    public function getNewestArticlesGroupedByCategory(ArticlesGroupedByCategorySearch $searchModel): array
    {
        /** @var Category[] $categories */
        $categories = Category::find()
            ->withoutDefaultCategory()
            ->orderByPriority()
            ->all();

        foreach ($categories as &$category) {
            $articles = $category
                ->getLatestArticles()
                ->bySource(
                    $searchModel->source,
                    $searchModel->country,
                    $searchModel->articlesLanguage
                )
                ->bySourceUrl($searchModel->sourceUrl)
                ->skipBanned($searchModel->skipBanned)
                ->limit($searchModel->limit)
                ->all();

            $category->setScenario(Category::SCENARIO_LATEST_ARTICLES_DEPRECATED);
            $category->populateRelation('latestArticles', $articles);
        }

        return $categories;
    }

    /**
     * @param Article[] $articles
     * @param array $sources
     * @param string|null $country
     * @param string|null $articlesLanguage
     * @param bool|null $skipBanned
     * @return Article[]
     */
    private function addSameArticlesAmount(array $articles, $sources, $sourcesUrls, ?string $country, ?string $articlesLanguage, ?bool $skipBanned): array
    {
        $ids = ArrayHelper::getColumn($articles, 'id');

        $sameArticlesAmount = Article::find()
            ->indexBy('same_article_id')
            ->select('COUNT(*)')
            ->skipBanned($skipBanned ?: false)
            ->byParentArticlesIds($ids);

        if (!Api::isRequestFromApp() || Api::versionLessThan(Api::V_2_20)) {
            $sameArticlesAmount->bySource($sources, $country, $articlesLanguage);
        } else {
            $sameArticlesAmount->bySourceUrl($sourcesUrls);
        }

        $sameArticlesAmount = $sameArticlesAmount->groupBy('same_article_id')
            ->cache(600)
            ->column(null, false);

        return array_map(static function (Article $article) use (&$sameArticlesAmount) {
            if (isset($sameArticlesAmount[$article->id])) {
                $article->same_articles_amount = $sameArticlesAmount[$article->id];
            } else {
                $article->same_articles_amount = 0;
            }
            return $article;
        }, $articles);
    }

    public function getAmountBySource(string $sourceId, Carbon $dateFrom, ?Carbon $dateTo = null): int
    {
        return Article::find()
            ->bySource($sourceId)
            ->createdAt($dateFrom, $dateTo)
            ->count('*', null, false);
    }

    public function getAmountByCountry(string $countryCode, Carbon $dateFrom, ?Carbon $dateTo = null, ?string $language = null): int
    {
        return Article::find()
            ->bySource(null, $countryCode, $language)
            ->createdAt($dateFrom, $dateTo)
            ->count('*', null, false);
    }

    public function getLastScrapedArticleDate(?string $countryCode = null, ?string $language = null): ?Carbon
    {
        $article = Article::find()
            ->newestFirst()
            ->limit(1)
            ->bySource(null, $country->code ?? null, $language->code ?? null)
            ->one();

        return $article->created_at ?? null;
    }

    public function increaseRating($articleId, App $app): int
    {
        return $this->rating($articleId, 1, $app);
    }

    public function decreaseRating($articleId, App $app): int
    {
        return $this->rating($articleId, -1, $app);
    }

    private function rating($articleId, int $value, App $app): int
    {
        Assertion::inArray($value, [1, -1]);

        if ($articleId && ($article = Article::findById($articleId))) {
            if (is_null($article->rating)) {
                $article->updateAttributes([
                    'rating' => 0
                ]);
            }

            $existsRating = $article
                ->getRatings()
                ->byArticleId($articleId)
                ->byAppId($app->id)
                ->one();

            if ($existsRating) {
                $ratingsCount = -1;
                $value = -$existsRating->rating;
                $saved = $existsRating->delete();
            } else {
                $rating = new ArticleRating([
                    'article_id' => $articleId,
                    'app_id' => $app->id,
                    'rating' => $value
                ]);

                $rating->country = $article->source->country;
                $saved = $rating->save();
                $ratingsCount = 1;
            }

            if ($saved) {
                $article->updateCounters([
                    'rating' => $value,
                    'ratings_count' => $ratingsCount
                ]);
            }

            return $article->rating;
        }

        throw new UserException(\t('Новость не найдена или была удалена'));
    }

    public function shared($articleId, App $app): int
    {
        if ($article = $this->getById($articleId)) {
            if (!$article->getShares()->andWhere([
                'app_id' => $app->id
            ])->exists()) {
                $share = new ArticleShare([
                    'article_id' => $articleId,
                    'country' => $app->country,
                    'app_id' => $app->id,
                    'platform' => $app->platform,
                    'date' => Carbon::now()->toDateString()
                ]);

                $share->save();

                $article->shares_count += 1;
            }

            return $article->shares_count;
        }

        throw new UserException('Article not found');
    }
}