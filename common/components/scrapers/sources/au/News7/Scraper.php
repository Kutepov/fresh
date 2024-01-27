<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\au\News7;

use common\components\guzzle\Guzzle;
use common\components\scrapers\common\ArticleBodyScraper;
use common\components\scrapers\common\helpers\PreviewHelper;
use common\components\scrapers\common\Config;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\XPathParserV2;
use common\components\scrapers\dto\ArticleItem;
use GuzzleHttp\Promise\Coroutine;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Class Scraper
 * @package common\components\scrapers\sources\au\News7;
 *
 * @Config (
 * timezone="Australia/Sydney", urls={
 * "https://7news.com.au/news/nsw",
 * "https://7news.com.au/sport",
 * "https://7news.com.au/politics",
 * "https://7news.com.au/business/finance",
 * "https://7news.com.au/news/world",
 * "https://7news.com.au/entertainment",
 * "https://7news.com.au/lifestyle"
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
            return $this->XPathParser->parseDescription($html, '//article//p');
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//div[@id='root']";
            $articlesNode = $html->filterXPath($selector);

            $articles = $articlesNode->filterXPath("
                                                           //div[contains(@class, 'StyledHeroContainer')]|
                                                           //div[contains(@class, 'css-c8eknp-StyledSNEnt')]|
                                                           //div[contains(@class, 'css-zaefrw-')]|
                                                           //div[contains(@class, 'StyledLandscapeStacked ')]|
                                                           //div[contains(@class, 'StyledPortrait')]|
                                                           //li[contains(@class, 'StyledListCard ')]");

            $lastAddedPublicationTime = $this->lastPublicationTime;
            $lastAddedPublicationTime = $lastAddedPublicationTime->setTimezone($this->timezone);
            $baseUrl = 'https://7news.com.au/';
            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {

                    if ($node->filterXPath("//span[contains(text(), 'Sponsored')]")->count()) {
                        continue;
                    }

                    $linkNode = $node->filterXPath("//a");
                    $pageLink = $linkNode->attr('href');

                    if (!filter_var($pageLink, FILTER_VALIDATE_URL)) {
                        $pageLink = $baseUrl.$pageLink;
                    }

                    $title = $node->filterXPath("//h1|//h2|//span[contains(@class, 'StyledHeadlineText')]|//span[contains(@class, 'StyledLinkText')]")->first()->text();

                    $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                    if ($this->isNeedSkipArticle($pageContent)) {
                        continue;
                    }
                    $html = new Crawler();
                    $html->addHtmlContent($pageContent->getBody()->getContents(), 'UTF-8');

                    $isFreeNode = $html->filterXPath(
                        "//meta[@name='article:content_tier']")
                        ->first();

                    if (!$isFreeNode->count() || $isFreeNode->attr('content') !== 'free') {
                        continue;
                    }


                    $dataArticle = $html->filterXPath(
                        "//meta[@property='article:published_time']")
                        ->first();

                    if (!$dataArticle->count()) {
                        continue;
                    }

                    $pubDateAttr = $dataArticle->attr('content');

                    $publicationDate = $this->createDateFromString($pubDateAttr);
                    $hashPreview = $this->previewHelper->getOgImageUrlHash($html);

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

    public function proxyEnablingAttempt(): ?int
    {
        return Guzzle::PROXY_ALWAYS_ENABLED;
    }
}
