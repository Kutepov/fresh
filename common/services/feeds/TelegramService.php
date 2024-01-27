<?php namespace common\services\feeds;

use common\models\Source;
use common\services\Requester;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DomCrawler\Crawler;

class TelegramService implements FeedFinder, FeedWithIdentifier
{
    private Requester $requester;
    public function __construct(Requester $requester)
    {
        $this->requester = $requester;
    }

    public function findByUrl(string $url): array
    {
        if ($this->validateAndFixUrlIfNeeded($url)) {
            $channelId = trim(parse_url($url, PHP_URL_PATH), '/');
            return $this->findById($channelId);
        }
        return [];
    }

    public function findById(string $id): array
    {
        $id = ltrim($id, '@');

        return $this->requester
            ->sendAsyncRequest(new Request('GET', $feedUrl = $this->buildFeedUrl($id)))
            ->then(function (ResponseInterface $response) use ($id, $feedUrl) {
                try {
                    $dom = new Crawler($response->getBody()->getContents());
                    return [
                        new FeedItem(
                            'https://t.me/' . $id,
                            $feedUrl,
                            $dom->filter('channel title')->text()
                        )
                    ];
                } catch (\Throwable $e) {
                    return [];
                }
            })
            ->wait();
    }

    public function buildFeedUrl($identifier): string
    {
        return env('TELEGRAM_RSS_URL') . 'rss/' . $identifier;
    }

    public function validateAndFixUrlIfNeeded(string &$url): bool
    {
        $url = trim($url);

        if (empty($url)) {
            return false;
        }

        $url = preg_replace('#^telegram://#', '', $url);

        if (!preg_match('#^https?#i', $url)) {
            $tmpFixedUrl = 'https://' . $url;
            if (validateUrl($tmpFixedUrl)) {
                if (!$this->validateHost($tmpFixedUrl)) {
                    return false;
                }
                $url = $tmpFixedUrl;
                return true;
            }
        } elseif ($this->validateHost($url)) {
            return true;
        }

        return false;
    }

    private function validateHost($url): bool
    {
        return clearHost($url) === 't.me';
    }

    public function getArticlesType(): string
    {
        return Source::TYPE_TELEGRAM;
    }
}