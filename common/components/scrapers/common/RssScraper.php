<?php declare(strict_types=1);

namespace common\components\scrapers\common;

use Carbon\Carbon;
use common\components\scrapers\common\helpers\PreviewHelper;
use common\components\scrapers\common\services\HashImageService;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\XPathParserV2;
use common\components\scrapers\dto\ArticleItem;
use common\services\feeds\RssService;
use GuzzleHttp\Promise\Coroutine;
use GuzzleHttp\Promise\Each;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use \SimpleXMLElement;
use Symfony\Component\DomCrawler\Crawler;

class RssScraper extends Scraper implements DeepPreviewFinder
{
    protected $hashImageService;
    protected $XPathParser;
    protected $rssService;
    protected $previewHelper;

    private const PREVIEW_ITEMS_LIMIT = 10;

    public function __construct(
        HashImageService $hashImageService,
        XPathParserV2    $XPathParser,
        RssService       $rssService,
        PreviewHelper    $previewHelper,
                         $config = [])
    {
        $this->XPathParser = $XPathParser;
        $this->hashImageService = $hashImageService;
        $this->rssService = $rssService;
        $this->previewHelper = $previewHelper;
        parent::__construct($config);
    }

    public function parseArticlesList(string $url, $isPreviewRequest = false): PromiseInterface
    {
        return Coroutine::of(function () use ($url, $isPreviewRequest) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $feed = $this->prepareFeed($response->getBody()->getContents());

            if ($isPreviewRequest) {
                $feed = array_slice($feed, 0 , self::PREVIEW_ITEMS_LIMIT);
            }

            $result = [];

            foreach ($feed as $item) {
                if ($this->lastPublicationTime && $this->lastPublicationTime >= $item->date) {
                    continue;
                }

                try {
                    if ($this->urlSkipRegexp && preg_match('#' . $this->urlSkipRegexp . '#siu', $item->url)) {
                        continue;
                    }
                } catch (\Throwable $e) {

                }

                try {
                    $previewImage = $item->image ? $this->hashImageService->hashImage($item->image) : null;

                    $article = new ArticleItem(
                        $item->url,
                        $item->title,
                        $item->date,
                        $previewImage
                    );

                    $crawler = new Crawler();
                    $crawler->addHtmlContent(nl2p($item->description, true, true) ?: $item->title);
                    $crawler = $crawler->filter('*');
                    $body = $this->XPathParser->parse($crawler);

                    $article->setBody($body);

                    $result[] = $article;

                } catch (\Throwable $e) {
                    $this->logArticleItemException($e);
                }
            }

//            if (!$isPreviewRequest) {
                $articlesWithoutImages = function () use (&$result) {
                    /** @var ArticleItem[] $result */
                    foreach ($result as $k => $article) {
                        if (!$article->getPreviewImage()) {
                            yield $this->sendAsyncRequestWithProxy(new Request('GET', $result[$k]->getUrl()))
                                ->then(function (ResponseInterface $pageContent) use (&$result, $k) {
                                    $html = new Crawler($pageContent->getBody()->getContents());
                                    $previewImage = $this->previewHelper->getOgImageUrlHash($html);
                                    $result[$k]->setPreviewImage($previewImage);
                                });
                        }
                    }
                };
                yield Each::ofLimit($articlesWithoutImages(), 16);
//            }

            yield $result;
        });
    }

    /**
     * @param $feed
     * @return RssFeedItem[]
     * @throws \Exception
     */
    protected function prepareFeed($feed): array
    {
        $feed = $this->rssService->prepareFeed($feed);

        try {
            $xml = new \SimpleXMLElement($feed, LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_NOCDATA);
        } catch (\Throwable $e) {
            return [];
        }

        if ($xml->channel) {
            return $this->fromRss($xml);
        }

        return $this->fromAtom($xml);
    }

    protected function fromRss(SimpleXMLElement $xml): array
    {
        if (!$xml->channel) {
            throw new \RuntimeException('Invalid feed.');
        }

        $this->adjustNamespaces($xml);
        $result = [];
        foreach ($xml->channel->item ?: $xml->item as $item) {
            // converts namespaces to dotted tags
            $this->adjustNamespaces($item);

            if (isset($item->{'dc_date'})) {
                $date = Carbon::parse((string)$item->{'dc_date'});
            } elseif (isset($item->pubDate)) {
                $date = Carbon::parse((string)$item->pubDate);
            } elseif (isset($item->date)) {
                $date = Carbon::parse((string)$item->date);
            }

            if ($item->title && $item->link && isset($date)) {
                $feedItem = new RssFeedItem([
                    'title' => $item->title,
                    'image' => $this->extractImageFromHtml((string)$item->description),
                    'description' => trim(nl2p((string)$item->description, true, true)) ?? null,
                    'author' => (string)$item->author ?: (string)$item->creator ?: null,
                    'url' => (string)$item->link,
                    'date' => $date
                ]);

                foreach ($item->enclosure as $attachment) {
                    $feedItem->attachments[] = new RssFeedItemAttachment([
                        'url' => (string)$attachment->attributes()->url,
                        'type' => (string)$attachment->attributes()->type,
                        'size' => (string)$attachment->attributes()->length

                    ]);
                }

                $result[] = $feedItem;
            }
        }

        return $result;
    }

    protected function fromAtom(SimpleXMLElement $xml): array
    {
        if (!in_array('http://www.w3.org/2005/Atom', $xml->getDocNamespaces(), true) &&
            !in_array('http://purl.org/atom/ns#', $xml->getDocNamespaces(), true)
        ) {
            throw new \RuntimeException('Invalid feed.');
        }

        $result = [];
        $entries = $xml->entry ?? $xml->item;

        foreach ($entries as $entry) {
            if ((string)$entry->title && (string)$entry->link->attributes()->href ?? false) {
                if (isset($entry->group->thumbnail)) {
                    $image = $entry->group->thumbnail->attributes()->url;
                } elseif (isset($entry->thumbnail)) {
                    $image = $entry->thumbnail->attributes()->url;
                } else {
                    $image = $this->extractImageFromHtml((string)$entry->description);
                }

                if (isset($entry->pubDate)) {
                    $date = Carbon::parse($entry->pubDate);
                } elseif (isset($entry->updated)) {
                    $date = Carbon::parse($entry->updated);
                } elseif (isset($entry->published)) {
                    $date = Carbon::parse($entry->published);
                }

                $result[] = new RssFeedItem([
                    'id' => $entry->id ?? null,
                    'title' => $entry->title,
                    'description' => nl2p($entry->description ?? $entry->content ?? $entry->summary ?? null, true, true),
                    'image' => $image,
                    'author' => $entry->author->name ?? null,
                    'url' => (string)$entry->link['href'],
                    'date' => $date
                ]);
            }
        }

        return $result;
    }

    protected function extractImageFromHtml(string $html): ?string
    {
        if (preg_match('#<img.+src=[\'"](?P<src>.+?)[\'"].*>#i', $html, $image)) {
            return $image[1];
        }

        return null;
    }

    /**
     * Generates better accessible namespaced tags.
     */
    protected function adjustNamespaces(SimpleXMLElement $el): void
    {
        foreach ($el->getNamespaces(true) as $prefix => $ns) {
            if ($prefix === '') {
                continue;
            }
            $children = $el->children($ns);
            foreach ($children as $tag => $content) {
                $el->{$prefix . '_' . $tag} = $content;
            }
        }
    }
}