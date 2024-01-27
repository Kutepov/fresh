<?php namespace common\services\feeds;

use common\components\scrapers\common\services\HashImageService;
use common\models\Source;
use common\services\Requester;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DomCrawler\Crawler;

class RssService implements FeedFinder
{
    private HashImageService $hashImageService;
    private Requester $requester;

    private const RSS_READER_USER_AGENT = 'Feedspot/1.0 (+https://www.feedspot.com/fs/fetcher; like FeedFetcher-Google)';
    private const FEEDS_NEEDED_CUSTOM_UA = [
        'www.popsugar.com'
    ];

    public function __construct(Requester $requester, HashImageService $hashImageService)
    {
        $this->requester = $requester;
        $this->hashImageService = $hashImageService;
    }

    public static function getUserAgent($url): ?string
    {
        $domain = parse_url($url, PHP_URL_HOST);

        if (in_array($domain, self::FEEDS_NEEDED_CUSTOM_UA, true)) {
            return self::RSS_READER_USER_AGENT;
        }

        return null;
    }

    /**
     * @param string $url site url
     * @return FeedItem[] array list of available feeds
     */
    public function findFeeds(string $url): array
    {
        return $this->findFeedsAsync($url)->wait();
    }

    public function findFeedsAsync($url): PromiseInterface
    {
        if (!validateUrl($url)) {
            return new FulfilledPromise([]);
        }

        return $this->requester->sendAsyncRequestWithProxy(new Request('GET', $url))
            ->then(function (ResponseInterface $response) use ($url) {
                /** Передан урл на фид, парсим иконку и заголовок главной страницы сайта */
                if ($this->isValidFeed($response)) {
                    /** В фиде не найден заголовок и/или картинка-лого, ищем на главной странице сайта */
                    if (!($feedItem = $this->extractMetaDataFromFeed($response, $url)) || !$feedItem->getIcon()) {
                        return $this->prepareExistsFeed($url)->then(function (array $feeds) use (&$feedItem) {
                            if ($feeds) {
                                /** @var FeedItem[] $feeds */
                                if ($feedItem) {
                                    $feedItem->setIcon($feeds[0]->getIcon());
                                    return [$feedItem];
                                }

                                return $feeds;
                            }

                            return [$feedItem];
                        });
                    }

                    return [$feedItem];
                }

                return $this->extractFeedsUrls($response->getBody()->getContents(), $url);
            })->otherwise(function ($exception) {
                return [];
            });
    }

    private function isValidFeed(ResponseInterface $response): bool
    {
        if (($contentType = $response->getHeader('Content-Type')[0]) &&
            (
                stripos($contentType, 'application/rss+xml') !== false ||
                stripos($contentType, 'application/xml') !== false ||
                stripos($contentType, 'application/atom+xml') !== false ||
                stripos($contentType, 'text/xml') !== false
            )
        ) {
            try {
                $xml = new \SimpleXMLElement(
                    $response->getBody()->getContents(),
                    LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_NOCDATA
                );

                $response->getBody()->seek(0);
                if (
                    $xml->channel ||
                    in_array('http://www.w3.org/2005/Atom', $xml->getDocNamespaces(), true) ||
                    in_array('http://purl.org/atom/ns#', $xml->getDocNamespaces(), true)
                ) {
                    return true;
                }
            } catch (\Throwable $e) {
                return false;
            }
        }

        return false;
    }

    function prepareFeed(string $xml): string
    {
        $feed = preg_replace('#<([^:<>"/ ]+):([^:<>"/ ]+)(?: [^>/]+|)>(.*?)</\1:\2>#su', '<$2>$3</$2>', $xml);
        return preg_replace('#<([^:<>"/ ]+):([^:<>"/ ]+)(?: [^>/]+|)>(.*?)</\1:\2>#su', '<$2>$3</$2>', $feed);
    }

    private function extractMetaDataFromFeed(ResponseInterface $response, $url): ?FeedItem
    {
        $xml = $response->getBody()->getContents();
        $feed = $this->prepareFeed($xml);

        try {
            $xml = new \SimpleXMLElement(
                $feed,
                LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_NOCDATA
            );
        } catch (\Throwable $e) {
            return null;
        }

        $title = (string)($xml->channel->title ?: (string)$xml->title ?: (string)$xml->channel->description ?: (string)$xml->feed->title);
        $icon = (string)$xml->channel->image->url;

        if (!$title) {
            return null;
        }

        return new FeedItem(
            (string)($xml->channel->link ?? $url),
            $url,
            $title,
            $icon
        );
    }

    private function isAtom(\SimpleXMLElement $xml): bool
    {
        return in_array('http://www.w3.org/2005/Atom', $xml->getDocNamespaces(), true) ||
            in_array('http://purl.org/atom/ns#', $xml->getDocNamespaces(), true);
    }

    private function prepareExistsFeed($url): PromiseInterface
    {
        $baseUrl = parse_url($url, PHP_URL_SCHEME) . '://' . parse_url($url, PHP_URL_HOST);
        $baseUrl = trim($baseUrl, '/');

        return $this->requester->sendAsyncRequestWithProxy(new Request('GET', $baseUrl))
            ->then(function (ResponseInterface $response) use ($url) {
                try {
                    $dom = new Crawler($response->getBody()->getContents());

                    return [
                        new FeedItem(
                            $url,
                            $url,
                            $this->extractTitle($dom),
                            $this->extractIconUrl($dom, $url)
                        )
                    ];
                } catch (\Throwable $e) {
                    return [];
                }
            });
    }

    private function extractFeedsUrls($html, $url): array
    {
        $dom = new Crawler($html);
        /** @var FeedItem[] $result */
        $result = [];

        $dom->filter('link')->each(function (Crawler $node) use (&$result, $url, $dom) {
            if (in_array(strtolower($node->attr('rel')), ['alternate', 'outline']) &&
                in_array(strtolower($node->attr('type')), ['application/rss+xml', 'application/atom+xml', 'text/xml'])
            ) {
                $feedUrl = $node->attr('href');
                if ($this->validateAndFixUrlIfNeeded($feedUrl)) {
                    $feedUrl = $this->addBaseUrlIfNeeded($feedUrl, $url);

                    $title = trim($node->attr('title'));

                    if (strtolower($title) === 'rss') {
                        $title = null;
                    }

                    $pageTitle = $this->extractTitle($dom);

                    if (!$title) {
                        $title = $pageTitle;
                    }

                    $feedItem = new FeedItem(
                        $url,
                        $feedUrl,
                        $title,
                        $this->extractIconUrl($dom, $url)
                    );

                    $feedItem->setSourceSiteTitle($pageTitle);

                    $result[] = $feedItem;
                }
            }
        });

        if (count($result) > 1) {
            $result = array_map(function (FeedItem $feedItem) {
                if (!$feedItem->getTitle() && $feedItem->getSourceSiteTitle() && $feedItem->getSourceSiteTitle() !== $feedItem->getTitle()) {
                    $feedItem->setTitle($feedItem->getSourceSiteTitle() . ' (' . $feedItem->getTitle() . ')');
                }
                return $feedItem;
            }, $result);
        } else {
            if (!$result[0]->getTitle()) {
                $result[0]->setTitle($result[0]->getSourceSiteTitle());
            }
            elseif ($result[0]->getTitle() !== $result[0]->getSourceSiteTitle()) {
                $result[0]->setTitle($result[0]->getSourceSiteTitle() . ' (' . $result[0]->getTitle() . ')');
            }
        }

        return $result;
    }

    private function extractIconUrl(Crawler $dom, $url): ?string
    {
        $result = null;

        if (!$this->validateIcon($result) && ($favicon = $dom->filter('meta[property="og:image"]')) && $favicon->count()) {
            $result = $favicon->attr('content');
        }

        if (($favicon = $dom->filter('head link[rel="apple-touch-icon"][sizes="120x120"]')) && $favicon->count()) {
            $result = $favicon->attr('href');
        }

        if (!$this->validateIcon($result) && ($favicon = $dom->filter('head link[rel="icon"][type="image/png"]')) && $favicon->count()) {
            $result = $favicon->attr('href');
        }

        if (!$this->validateIcon($result) && ($favicon = $dom->filter('head link[rel="icon"][type="image/jpg"]')) && $favicon->count()) {
            $result = $favicon->attr('href');
        }

        if (!is_null($result)) {
            $result = $this->addBaseUrlIfNeeded($result, $url);
        }

        return $result;
    }

    private function validateIcon(?string $url): bool
    {
        if (!$url) {
            return false;
        }

        if (strtolower(pathinfo($url, PATHINFO_EXTENSION)) === 'ico') {
            return false;
        }

        return true;
    }

    private function extractTitle(Crawler $dom): ?string
    {
        return $dom->filter('title')->first()->text();
    }

    private function addBaseUrlIfNeeded($url, $originalUrl): string
    {
        if (!preg_match('#^https?://#', $url)) {
            $parsedUrl = parse_url($originalUrl);
            $url = $parsedUrl['scheme'] . '://' . $parsedUrl['host'] . '/' . ltrim($url, '/');
        }

        return $url;
    }

    public function validateAndFixUrlIfNeeded(string &$url): bool
    {
        $url = preg_replace('#^rss://#', '', $url);
        $url = preg_replace('#^feed://#i', 'https://', $url);

        return validateUrl($url);
    }

    public function buildFeedUrl($identifier): string
    {
        return $identifier;
    }

    public function findByUrl(string $url): array
    {
        if ($this->validateAndFixUrlIfNeeded($url)) {
            return $this->findFeeds($url);
        }

        return [];
    }

    public function getArticlesType(): string
    {
        return Source::TYPE_WEBVIEW;
    }
}