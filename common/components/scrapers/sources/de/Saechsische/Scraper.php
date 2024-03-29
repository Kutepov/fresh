<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\de\Saechsische;

use common\components\scrapers\common\Config;
use common\components\scrapers\common\services\HashImageService;
use common\components\scrapers\dto\ArticleBody;
use common\components\scrapers\dto\ArticleBodyNode;
use common\components\scrapers\dto\ArticleItem;
use GuzzleHttp\Promise\Coroutine;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Class Scraper
 * @package common\components\scrapers\sources\de\Saechsische
 *
 * @Config (
 * timezone="Europe/Berlin", urls={
 * "https://www.saechsische.de/sitemap/myfresh/thema/welt"
 * })
 */
class Scraper extends \common\components\scrapers\common\Scraper
{

    /**
     * @var HashImageService
     */
    private $hashImageService;

    public function __construct(
        HashImageService $hashImageService,
        $config = []
    )
    {
        $this->hashImageService = $hashImageService;

        parent::__construct($config);
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addXmlContent($response->getBody()->getContents(), 'UTF-8');

            $articles = $html->filterXPath("//channel//item");

            $lastAddedPublicationTime = $this->lastPublicationTime->setTimezone($this->timezone);

            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    $title = $node->filterXPath('//title')->text();

                    $pubDateAttr = $node->filterXPath('//pubDate')->text();
                    $pageLink = $node->filterXPath('//link')->text();

                    $thumbnail = $node->filterXPath("//enclosure")->attr('url');
                    $hashPreview = $thumbnail ? $this->hashImageService->hashImage($thumbnail) : null;

                    $publicationDate = $this->createDateFromString($pubDateAttr);

                    if ($publicationDate > $lastAddedPublicationTime) {
                        $article = new ArticleItem($pageLink, $title, $publicationDate, $hashPreview);

                        $body = new ArticleBody();
                        $body->add(new ArticleBodyNode('paragraph', $node->filterXPath('//description')->text()));
                        $article->setBody($body);

                        $result[] = $article;
                    }
                } catch (\Throwable $exception) {
                    $this->logArticleItemException($exception, $pageLink);
                }
            }

            yield $result;
        });
    }
}
