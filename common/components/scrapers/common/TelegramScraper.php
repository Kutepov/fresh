<?php declare(strict_types=1);

namespace common\components\scrapers\common;

use Carbon\Carbon;
use common\components\scrapers\dto\ArticleBody;
use common\components\scrapers\dto\ArticleBodyNode;
use common\components\scrapers\dto\ArticleBodyNodeVideoPreview;
use common\components\scrapers\dto\ArticleItem;
use common\models\Article;
use GuzzleHttp\Promise\Coroutine;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use Symfony\Component\DomCrawler\Crawler;
use \SimpleXMLElement;

class TelegramScraper extends RssScraper
{
    /** Лимит количества последних постов */
    private const LIMIT = 50;

    public function parseArticlesList(string $url, $isPreviewRequest = false): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequest(new Request('GET', $url));
            $feed = $this->prepareFeed($response->getBody()->getContents());
            $result = [];

            foreach ($feed as $item) {
                if ($this->lastPublicationTime && $this->lastPublicationTime >= $item->date) {
                    continue;
                }

                try {
                    $article = new ArticleItem(
                        $item->url,
                        strip_tags($item->description),
                        Carbon::parse($item->date),
                        $item->image ? $this->hashImageService->hashImage(
                            $this->fixImageUrl($item->image)
                        ) : null
                    );

                    $crawler = new Crawler();
                    $crawler->addHtmlContent($item->description);
                    $crawler = $crawler->filter('*');
                    $body = new ArticleBody();

                    $description = $this->XPathParser->parse($crawler);

                    $gallery = [];
                    foreach ($item->attachments as $attachment) {
                        if ($attachment->isImage) {
                            $gallery[] = $this->hashImageService->hashImage($this->fixImageUrl($attachment->url));
                        } elseif ($attachment->isVideo) {
                            $body->add(
                                new ArticleBodyNodeVideoPreview(
                                    $this->hashImageService->hashImage($this->fixImageUrl($attachment->getPreview())),
                                    $item->url
                                )
                            );
                        }
                    }

                    if (count($gallery) > 1) {
                        $body->add(new ArticleBodyNode(Article::BODY_PART_CAROUSEL, $gallery));
                    } elseif (count($gallery) === 1) {
                        $body->add(new ArticleBodyNode(Article::BODY_PART_IMAGE, $gallery[0]));
                    }

                    foreach ($description->getNodes() as $part) {
                        $body->add($part);
                    }

                    $article->setBody($body);

                    $result[] = $article;

                } catch (\Throwable $e) {
                    $this->logArticleItemException($e);
                }
            }

            yield $result;
        });
    }

    private function fixImageUrl($url)
    {
        return str_replace([
            env('TELEGRAM_RSS_URL'),
            env('TELEGRAM_RSS_LOCAL_URL')
        ], [
            env('TELEGRAM_RSS_EXTERNAL_URL'),
            env('TELEGRAM_RSS_EXTERNAL_URL')
        ], $url);
    }

    protected function fromRss(SimpleXMLElement $xml): array
    {
        if (!$xml->channel) {
            throw new \RuntimeException('Invalid feed.');
        }

        $this->adjustNamespaces($xml);
        $result = [];
        foreach ($xml->channel->item as $item) {
            // converts namespaces to dotted tags
            $this->adjustNamespaces($item);

            if (isset($item->{'dc_date'})) {
                $date = Carbon::parse((string)$item->{'dc_date'});
            } elseif (isset($item->pubDate)) {
                $date = Carbon::parse((string)$item->pubDate);
            }

            if ($item->title && $item->link && isset($date)) {
                $description = trim((string)$item->description);
                if (!empty($description)) {
                    $description = $this->prepareDescription($description);
                }

                $feedItem = new RssFeedItem([
                    'id' => $item->id ?? null,
                    'image' => $this->extractImageFromHtml((string)$item->description),
                    'description' => $description,
                    'author' => $item->author ?? null,
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

    private function prepareDescription($html): string
    {
        $html = nl2p($html);
        $html = strip_tags($html, ['p']);
        $html = str_replace('<p></p>', '', $html);

        return $html;
    }
}