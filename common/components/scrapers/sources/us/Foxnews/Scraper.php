<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\us\Foxnews;

use common\components\scrapers\common\ArticleBodyScraper;
use common\components\scrapers\common\helpers\PreviewHelper;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\XPathParserV2;
use common\components\scrapers\dto\ArticleItem;
use GuzzleHttp\Promise\Coroutine;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DomCrawler\Crawler;
use common\components\scrapers\common\Config;

/**
 * Class Scraper
 * @package common\components\scrapers\sources\us\Foxnews
 *
 * @Config (timezone="America/New_York", urls={
 * "https://www.foxbusiness.com/",
 * "https://www.foxnews.com/entertainment",
 * "https://www.foxnews.com/sports",
 * "https://www.foxnews.com/lifestyle",
 * "https://www.foxnews.com/us",
 * "https://www.foxnews.com/politics",
 * "https://www.foxnews.com/media",
 * "https://www.foxnews.com/opinion"
 * })
 */
class Scraper extends \common\components\scrapers\common\Scraper implements ArticleBodyScraper
{
    /**
     * @var PreviewHelper
     */
    private $previewHelper;

    /**
     * @var XPathParserV2
     */
    private $XPathParser;

    public function __construct(
        PreviewHelper $previewHelper,
        XPathParserV2 $XPathParser,
                      $config = []
    )
    {
        $this->previewHelper = $previewHelper;
        $this->XPathParser = $XPathParser;

        parent::__construct($config);
    }

    public function parseArticleBody(string $url): PromiseInterface
    {
        return $this->sendAsyncRequestWithProxy(new Request('GET', $url))->then(function (ResponseInterface $response) {
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents());
            return $this->XPathParser->parseDescription($html, '//div[@class="article-body"]//p[1]|//div[@class="video-meta"]//h1');
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler($pageContent->getBody()->getContents());

            $selector = "//div[@class='page-content']";
            $articlesNode = $html->filterXPath($selector)->first();

            $articles = $articlesNode->filterXPath("//article");

            $lastAddedPublicationTime = $this->lastPublicationTime;
            $result = [];
            $baseUrl = 'https://www.foxnews.com';
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    $urlNode = $node->filterXPath("//*[@class='title']//a")->first();
                    if (!$urlNode->count()) {
                        continue;
                    }
                    $pageLink = $urlNode->attr('href');

                    if (!filter_var($pageLink, FILTER_VALIDATE_URL)) {
                        $pageLink = $baseUrl . $pageLink;
                    }

                    $title = $urlNode->text();
                    $dateNode = $node->filterXPath("//time")->first();

                    $html = null;
                    if (!$dateNode->count()) {
                        $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                        $html = new Crawler($pageContent->getBody()->getContents());
                    } else {
                        $date = $dateNode->attr('time');
                    }

                    if (!$html) {
                        $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                        $html = new Crawler($pageContent->getBody()->getContents());
                    }

                    if (!$dateNode->count()) {
                        $dataArticle = $html->filterXPath(
                            "//script[@type='application/ld+json']")
                            ->first();

                        if (!$dataArticle->count()) {
                            continue;
                        }

                        $dataArticle = json_decode($dataArticle->text(), true);

                        if (!isset($dataArticle['datePublished'])) {
                            continue;
                        }

                        $date = $dataArticle['datePublished'];
                    }

                    $hashPreview = $this->previewHelper->getOgImageUrlHash($html);

                    $publicationDate = $this->createDateFromString($date);

                    if ($publicationDate > $lastAddedPublicationTime) {
                        $result[] = new ArticleItem($pageLink, $title, $publicationDate, $hashPreview);
                    }
                } catch (\Throwable $exception) {
                    $this->logArticleItemException($exception, $pageLink);
                }
            }
            yield $result;
        });
    }
}