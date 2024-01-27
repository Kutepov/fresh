<?php namespace common\services\feeds;

use common\models\Source;

class YouTubeService implements FeedFinder, FeedWithIdentifier
{
    private RssService $service;

    public function __construct(RssService $service)
    {
        $this->service = $service;
    }

    public function findByUrl(string $url): array
    {
        if ($this->validateAndFixUrlIfNeeded($url)) {
            $channelId = trim(parse_url($url, PHP_URL_PATH), '/');
            return $this->findById($channelId, $url);
        }

        return [];
    }

    public function findById(string $id, ?string $originalUrl = null): array
    {
//        $id = ltrim($id, '@');

        $feeds = $this->service->findFeeds($this->buildFeedUrl($id));

        if ($originalUrl) {
            $feeds = array_map(static function (FeedItem $feedItem) use ($originalUrl) {
                $feedItem->setUrl($originalUrl);
                return $feedItem;
            }, $feeds);
        }

        return $feeds;
    }

    public function buildFeedUrl($identifier): string
    {
        return 'https://www.youtube.com/' . $identifier;
    }

    public function validateAndFixUrlIfNeeded(string &$url): bool
    {
        $url = trim($url);

        if (empty($url)) {
            return false;
        }

        $url = preg_replace('#^youtube://#', '', $url);

        if (!preg_match('#^https?#i', $url)) {
            $tmpFixedUrl = 'https://' . $url;
            if (validateUrl($tmpFixedUrl)) {
                if (!$this->validateHost($tmpFixedUrl)) {
                    return false;
                }
                $url = $this->clearUrl($tmpFixedUrl);
                return true;
            }
        } elseif ($this->validateHost($url)) {
            $url = $this->clearUrl($url);
            return true;
        }

        return false;
    }

    private function clearUrl($url): string
    {
        $queryString = parse_url($url, PHP_URL_QUERY);
        parse_str($queryString, $queryArr);
        if (isset($queryArr['channel_id'])) {
            return 'https://www.youtube.com/feeds/videos.xml?channel_id=' . $queryArr['channel_id'];
        }

        $path = parse_url($url, PHP_URL_PATH);
        $path = explode('/', $path);
        if ($path[1] === 'channel' || $path[1] === 'user') {
            return 'https://www.youtube.com/' . $path[1] . '/@' . ltrim($path[2], '@');
        }

        $path[1] = '@' . ltrim($path[1], '@');
        return 'https://www.youtube.com/' . $path[1];
    }

    private function validateHost($url): bool
    {
        return in_array(clearHost($url), ['youtube.com', 'youtu.be']);
    }

    public function getArticlesType(): string
    {
        return Source::TYPE_YOUTUBE_PREVIEW;
    }
}