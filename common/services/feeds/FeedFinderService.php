<?php declare(strict_types=1);

namespace common\services\feeds;

use common\components\scrapers\common\DeepPreviewFinder;
use common\components\scrapers\common\RssScraper;
use common\components\scrapers\common\Scraper;
use common\components\scrapers\common\TelegramScraper;
use common\components\scrapers\common\YoutubeRssScraper;
use common\components\scrapers\dto\ArticleItem;
use common\models\Source;

class FeedFinderService
{
    private const DEFAULT_SERVICE = RssService::class;

    private const SERVICES = [
        Source::TYPE_YOUTUBE => YouTubeService::class,
        Source::TYPE_TELEGRAM => TelegramService::class
    ];

    private const SCRAPERS = [
        Source::TYPE_YOUTUBE => YoutubeRssScraper::class,
        Source::TYPE_TELEGRAM => TelegramScraper::class,
        'rss' => RssScraper::class
    ];

    public const SCRAPERS_LABELS = [
        Source::TYPE_YOUTUBE => 'YouTube',
        Source::TYPE_TELEGRAM => 'Telegram',
        'rss' => 'RSS'
    ];

    /**
     * @var FeedFinder[]
     */
    private static $INSTANCES = [];

    /** @var FeedFinder[]|Scraper[] */
    private static $SCRAPERS_INSTANCES = [];

    public function __construct()
    {
        foreach (self::SERVICES as $type => $class) {
            self::$INSTANCES[$type] = \Yii::$container->get($class);
        }

        self::$INSTANCES['rss'] = \Yii::$container->get(self::DEFAULT_SERVICE);

        foreach (self::SCRAPERS as $type => $class) {
            self::$SCRAPERS_INSTANCES[$type] = \Yii::$container->get($class);
        }
    }

    public function isFeedUrl(&$url): bool
    {
        foreach (self::$INSTANCES as $instance) {
            if (!($instance instanceof RssService) && $instance->validateAndFixUrlIfNeeded($url)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param $url
     * @param string|string[] $type
     * @return bool
     */
    public function isValidUrlForType($url, $sourceType): bool
    {
        foreach (self::$INSTANCES as $instanceType => $instance) {
            if ($instanceType === 'rss') {
                $instanceType = Source::TYPE_FULL_ARTICLE;
            }

            if ($instance->validateAndFixUrlIfNeeded($url)) {
                if (
                    $sourceType === $instanceType ||
                    (is_array($sourceType) && in_array($instanceType, $sourceType, true))

                ) {
                    return true;
                }

                return false;
            }
        }

        return false;
    }

    public function validateAndFixUrlIfNeeded(&$url): bool
    {
        foreach (self::$INSTANCES as $instance) {
            if ($instance->validateAndFixUrlIfNeeded($url)) {
                return true;
            }
        }

        return false;
    }

    public function findFeedsByUrl($url): array
    {
        foreach (self::$INSTANCES as $instance) {
            if ($instance->validateAndFixUrlIfNeeded($url)) {
                return $instance->findByUrl($url);
            }
        }

        return [];
    }

    /**
     * @param $id
     * @param string|string[] $type
     * @return array
     */
    public function findFeedsByAlias($id, $sourceType): array
    {
        $result = [];
        foreach (self::$INSTANCES as $instanceType => $instance) {
            if ($instanceType === 'rss') {
                $instanceType = Source::TYPE_FULL_ARTICLE;
            }

            if (
                $instance instanceof FeedWithIdentifier &&
                (
                    $sourceType === $instanceType ||
                    (is_array($sourceType) && in_array($instanceType, $sourceType, true))
                )

            ) {
                $result = array_merge($result, $instance->findById($id));
            }
        }

        return $result;
    }

    public function getFeedInfoByUrlId($urlId): ?FeedItem
    {
        $type = parse_url($urlId, PHP_URL_SCHEME);
        $feeds = $this->getInstance($type)->findByUrl($urlId);
        return reset($feeds) ?: null;
    }

    private function getFeedUrlByUrlId($urlId): ?string
    {
        $url = parse_url($urlId);
        $type = $url['scheme'];

        if (!array_key_exists($type, self::SCRAPERS)) {
            throw new \RuntimeException('Unsupported feed type: ' . $urlId);
        }

        $fullUrl = $url['host'] . ':' . $url['path'] . (isset($url['query']) ? '?' . $url['query'] : '');

        if ($type !== 'rss') {
            $feeds = $this->getInstance($type)->findByUrl($fullUrl);
            /** @var FeedItem $feed */
            $feed = reset($feeds);
            if ($feed) {
                $feedUrl = $feed->getFeedUrl();
            }
        } else {
            $feedUrl = $fullUrl;
        }

        return $feedUrl ?? null;
    }

    /**
     * @param string $id
     * @param int $limit
     * @return ArticleItem[]
     */
    public function getFeedItemsById(string $id, int $limit = 20): array
    {
        $url = parse_url($id);
        $type = $url['scheme'];

        $scraper = self::$SCRAPERS_INSTANCES[$type];
        $service = self::$INSTANCES[$type];

        $feedUrl = $this->getFeedUrlByUrlId($id);

        if (!empty($feedUrl)) {
            if ($scraper instanceof DeepPreviewFinder) {
                $feedItems = $scraper->parseArticlesList($feedUrl, true)->wait();
            }
            else {
                $feedItems = $scraper->parseArticlesList($feedUrl)->wait();
            }

            /** Устанавливаем тип новости для правильного превью в приложении */
            $feedItems = array_map(static function (ArticleItem $articleItem) use ($service, $feedUrl) {
                $articleItem->setType($service->getArticlesType());
                $articleItem->setSourceName(extractDomainFromUrl($feedUrl));
                $articleItem->setSourceDomain($articleItem->getSourceName());
                return $articleItem;
            }, $feedItems);

            return array_values(
                array_slice(
                    $feedItems,
                    0,
                    $limit
                )
            );
        }

        return [];
    }

    private function getInstance($type): FeedFinder
    {
        if (!array_key_exists($type, self::SERVICES)) {
            $type = 'rss';
        }

        return self::$INSTANCES[$type];
    }

    public function getAvailableScrapers(): array
    {
        return self::SCRAPERS;
    }
}