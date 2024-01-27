<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\us\Abcnews;

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
 * @package common\components\scrapers\sources\us\Ancbews
 *
 * @Config (timezone="America/New_York", urls={
 * "https://abcnews.go.com/US",
 * "https://abcnews.go.com/International",
 * "https://abcnews.go.com/Business",
 * "https://abcnews.go.com/Lifestyle",
 * "https://abcnews.go.com/Politics",
 * "https://abcnews.go.com/Entertainment",
 * "https://abcnews.go.com/Technology",
 * "https://abcnews.go.com/Health",
 * "https://abcnews.go.com/Sports"
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
            return $this->XPathParser->parseDescription($html, '//article//p[1]');
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler($pageContent->getBody()->getContents());

            $selector = "//div[@id='abcnews']";
            $articlesNode = $html->filterXPath($selector)->first();

            $articles = $articlesNode->filterXPath("//div[@class='ContentList__Item']|//li[@class='LatestHeadlines__item']|//section[@class='ContentRoll__Item']");

            $lastAddedPublicationTime = $this->lastPublicationTime;
            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    $urlNode = $node->filterXPath("//a")->first();
                    if (!$urlNode->count()) {
                        continue;
                    }
                    $pageLink = $urlNode->attr('href');

                    $title = $urlNode->text();

                    if (!$title) {
                        continue;
                    }

                    $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                    if ($this->isNeedSkipArticle($pageContent)) {
                        continue;
                    }

                    $html = new Crawler($pageContent->getBody()->getContents());

                    $hashPreview = $this->previewHelper->getOgImageUrlHash($html, 'abc_news_default');
                    $dateNode = $html->filterXPath('//meta[@property="lastPublishedDate"]');

                    if (!$dateNode->count()) {
                        continue;
                    }

                    $date = $dateNode->attr('content');

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