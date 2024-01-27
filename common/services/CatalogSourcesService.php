<?php namespace common\services;

use api\models\search\CatalogSourceUrlSearch;
use Assert\Assertion;
use Assert\AssertionFailedException;
use common\components\scrapers\dto\ArticleItem;
use common\models\App;
use common\models\Article;
use common\models\CatalogSearchHistory;
use common\models\Folder;
use common\models\pivot\SourceUrlSubscription;
use common\models\Source;
use common\models\SourceUrl;
use common\services\feeds\FeedFinderService;
use common\services\feeds\FeedItem;
use Yii;
use yii\web\UnauthorizedHttpException;

class CatalogSourcesService
{
    private HashtagsService $hashtags;
    private FeedFinderService $feedFinderService;
    private DbManager $dbManager;

    private SourcesService $sourcesService;

    public function __construct(
        HashtagsService   $hashtags,
        FeedFinderService $feedFinderService,
        DbManager         $dbManager,
        SourcesService    $sourcesService
    )
    {
        $this->sourcesService = $sourcesService;
        $this->feedFinderService = $feedFinderService;
        $this->hashtags = $hashtags;
        $this->dbManager = $dbManager;
    }

    /**
     * @throws AssertionFailedException
     */
    public function find(CatalogSourceUrlSearch $searchForm, ?App $app = null): array
    {
        if ($searchForm->type) {
            Assertion::choice($searchForm->type, Source::AVAILABLE_TYPES);
        } else {
            $searchForm->type = Source::SITE_RSS_TYPES;
        }

        if ($this->hashtags->isValidHashtag($searchForm->query)) {
            $sourcesUrls = $this->findByHashTag(
                $searchForm
            );
            $logType = CatalogSearchHistory::TYPE_HASHTAG;

        } elseif ($this->validateAndFixUrlIfNeeded($searchForm->query)) {
            $sourcesUrls = $this->findByUrl($searchForm);
            $logType = CatalogSearchHistory::TYPE_URL;
        } else {
            $sourcesUrls = $this->findByKeyword($searchForm);
            $logType = CatalogSearchHistory::TYPE_KEYWORD;
        }

        if (!count($sourcesUrls)) {
            $this->createSearchHistoryLog(
                $searchForm->query,
                $searchForm->type,
                $logType,
                $app
            );
        }

        return $sourcesUrls;
    }

    private function findByHashTag(CatalogSourceUrlSearch $searchForm): array
    {
        return $this
            ->getQuery(
                $searchForm->type,
                $searchForm->country,
                null,
                true,
                $searchForm->offset,
                $searchForm->limit
            )
            ->byHashTag($searchForm->query)
            ->all();
    }

    private function findByUrl(CatalogSourceUrlSearch $searchForm): array
    {
        $sources = $this
            ->getQuery(
                $searchForm->type,
                null,
                null,
                false,
                $searchForm->offset,
                $searchForm->limit
            )
            ->byUrl($searchForm->query, true)
            ->all();

        $strict = true;
        if ($searchForm->type !== Source::TYPE_YOUTUBE && !count($sources)) {
            $strict = false;
            $sources = $this
                ->getQuery(
                    $searchForm->type,
                    null,
                    null,
                    false,
                    $searchForm->offset,
                    $searchForm->limit
                )
                ->byUrl($searchForm->query)
                ->all();
        }

        if ((!$strict || !count($sources)) && $this->feedFinderService->isValidUrlForType($searchForm->query, $searchForm->type)) {
            $remoteSources = $this->feedFinderService->findFeedsByUrl($searchForm->query);

            $remoteSources = array_map(static function (FeedItem $feedItem) {
                return $feedItem->getSourceUrlInstance();
            }, $remoteSources);
        }

        return $remoteSources ?? $sources;
    }

    /**
     * @return SourceUrl[]
     */
    private function findByKeyword(CatalogSourceUrlSearch $searchForm): array
    {
        /** @var SourceUrl[] $sources */
        $sources = $this
            ->getQuery(
                $searchForm->type,
                $searchForm->country,
                $searchForm->articlesLanguage,
                false,
                $searchForm->offset,
                $searchForm->limit
            )
            ->excludeCountries(['RU', 'BY'])
            ->byKeyword($searchForm->query)
            ->byType($searchForm->type)
            ->all();

        if (!count($sources) && $searchForm->query[0] === '@') {
            $feedsSources = $this->feedFinderService->findFeedsByAlias($searchForm->query, $searchForm->type);
            $feedsSources = array_map(static function (FeedItem $feedItem) {
                return $feedItem->getSourceUrlInstance();
            }, $feedsSources);

            return array_merge($sources, $feedsSources);
        }

        return $sources;
    }

    /**
     * @param string|string[] $type
     */
    private function getQuery($type, ?string $country, ?string $language, $strict = false, $offset = 0, $limit = 15): \common\queries\SourceUrl
    {
        return SourceUrl::find()
            ->byType($type)
            ->enabled()
            ->byCountry($country, $strict)
            ->byLanguage($language, $strict)
            ->offset($offset)
            ->mostPopularFirst()
            ->orderedByName()
            ->limit($limit);
    }

    public function preview($id)
    {
        if (!is_numeric($id) || !($sourceUrl = SourceUrl::findOne($id))) {
            $items = $this->feedFinderService->getFeedItemsById($id, 30);

            return array_map(static function (ArticleItem $articleItem) {
                return Article::instanceFromDto(
                    $articleItem,
                    $articleItem->getPublicationDate()
                );
            }, $items);
        }

        return Article::find()->bySourceUrl($sourceUrl->id)
            ->limit(20)
            ->newestFirst()
            ->all();
    }

    private function getOrCreateSourceUrl($id, ?string $country = null, ?string $folderId = null): SourceUrl
    {
        return $this->dbManager->wrap(function () use ($id, $country, $folderId) {
            if (is_numeric($id) && $sourceUrl = SourceUrl::find()->where(['sources_urls.id' => $id])->enabled()->one()) {
                if (!$sourceUrl->source->default && Yii::$app->user->isGuest) {
                    throw new UnauthorizedHttpException();
                }
                return $sourceUrl;
            }

            if ($feed = $this->feedFinderService->getFeedInfoByUrlId($id)) {
                if ($sourceUrl = SourceUrl::find()->byUrl($feed->getFeedUrl(), true)->one()) {
                    if (!$sourceUrl->source->default && Yii::$app->user->isGuest) {
                        throw new UnauthorizedHttpException();
                    }
                    return $sourceUrl;
                }

                if (Yii::$app->user->isGuest) {
                    throw new UnauthorizedHttpException();
                }

                $categoryId = null;
                if ($folderId && ($folder = Folder::findById($folderId))) {
                    $categoryId = $folder->category_id;
                }

                return $this->sourcesService->createSource(
                    $feed->getType(),
                    $feed->getTitle(),
                    $feed->getUrl(),
                    $feed->getFeedUrl(),
                    $feed->getParserClass(),
                    $feed->getIcon(),
                    $country,
                    $categoryId,
                    false
                );
            }

            throw new \RuntimeException('Feed not found: ' . $id);
        });
    }

    public function batchSubscribeToSourcesUrls($ids, bool $pushNotifications, App $app)
    {
        Assertion::isArray($ids);
        foreach ($ids as $id) {
            (new SourceUrlSubscription([
                'source_url_id' => $id,
                'app_id' => $app->id,
                'push' => (int)$pushNotifications
            ]))->save();
        }
    }

    public function batchUnsubscribeFromSourcesUrls($ids, App $app)
    {
        Assertion::isArray($ids);
        $subscriptions = SourceUrlSubscription::find()
            ->where(['app_id' => $app->id])
            ->andWhere(['source_url_id' => $ids])
            ->all();

        foreach ($subscriptions as $subscription) {
            $subscription->delete();
        }
    }

    public function subscribeToSourceUrl($id, App $app, ?string $country = null, ?string $defaultFolderId = null): SourceUrl
    {
        $sourceUrl = $this->getOrCreateSourceUrl($id, $country, $defaultFolderId);

        (new SourceUrlSubscription([
            'source_url_id' => $sourceUrl->id,
            'app_id' => $app->id
        ]))->save();

        return $sourceUrl;
    }

    public function unsubscribeFromSourceUrl($id, App $app)
    {
        if ($subscription = $app->getSourcesUrlsSubscriptions()
            ->andWhere(['source_url_id' => $id])
            ->one()) {
            $subscription->delete();
        }
    }

    private function validateAndFixUrlIfNeeded(?string &$url): bool
    {
        if (is_null($url)) {
            return false;
        }

        $url = trim($url);

        if (empty($url)) {
            return false;
        }

        if (!preg_match('#^https?#i', $url)) {
            $tmpFixedUrl = 'https://' . $url;
            if (validateUrl($tmpFixedUrl) &&
                $this->feedFinderService->validateAndFixUrlIfNeeded($tmpFixedUrl)
            ) {
                $url = $tmpFixedUrl;
                return true;
            }
        } else {
            return $this->feedFinderService->validateAndFixUrlIfNeeded($url);
        }

        return false;
    }

    private function createSearchHistoryLog($query, $section, $type, ?App $app = null): void
    {
        try {
            if ($app && $prevQuery = CatalogSearchHistory::find()->where([
                    'app_id' => $app->id
                ])->orderBy(['id' => SORT_DESC])->one()) {
                /** Удаляем предыдущий такой же запрос */
                if ($prevQuery->query === $query) {
                    $prevQuery->delete();
                /** Удаляем предыдущий не полный запрос (live search) */
                } elseif (mb_strlen($query) > mb_strlen($prevQuery->query) && stripos($query, $prevQuery->query) === 0) {
                    $prevQuery->delete();
                }
            }

            $log = new CatalogSearchHistory([
                'app_id' => $app->id ?? null,
                'query' => $query,
                'section' => !is_string($section) ? 'rss': $section,
                'type' => $type
            ]);
            $log->save();
        }
        catch (\Throwable $e) {}
    }
}