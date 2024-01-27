<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\pl\WpPl;

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
 * @package common\components\scrapers\sources\pl\WpPl
 *
 * @Config (
 * timezone="Europe/Kiev", urls={
 * "https://wiadomosci.wp.pl/koronawirus",
 * "https://wiadomosci.wp.pl/najnowsze",
 * "https://wiadomosci.wp.pl/nauka",
 * "https://wiadomosci.wp.pl/polityka",
 * "https://wiadomosci.wp.pl/polska",
 * "https://wiadomosci.wp.pl/spoleczenstwo",
 * "https://wiadomosci.wp.pl/swiat",
 * "https://wiadomosci.wp.pl/tylko-w-wp",
 * "https://wiadomosci.wp.pl/wideo"
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
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');
            return $this->XPathParser->parseDescription($html, '//p[@class="sc-bwzfXH sc-htpNat sc-lkqHmb kDHxcV"][1]');
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler($response->getBody()->getContents());

            $selector = "//div[contains(@class, 'teasersListing')]|//div[contains(@class, 'content')]";
            $articlesNode = $html->filterXPath($selector)->first();

            $baseLink = 'https://wiadomosci.wp.pl';

            $articles = $articlesNode->filterXPath("
            //a
        ");
            $lastAddedPublicationTime = $this->lastPublicationTime;

            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    if ($node->filterXPath('//h2')->count() === 0) {
                        continue;
                    }
                    if (!filter_var($node->attr('href'), FILTER_VALIDATE_URL)) {
                        $pageLink = $baseLink . $node->attr('href');
                    }
                    else {
                        $pageLink = $node->attr('href');
                    }
                    $title = $node->filterXPath("//h2")->first()->text();
                    $innerPageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                    if ($this->isNeedSkipArticle($innerPageContent)) {
                        continue;
                    }
                    $html = new Crawler($innerPageContent->getBody()->getContents());


                    $articlePubDate = $html->filterXPath("//meta[@property='article:published_time']")->first();
                    if ($articlePubDate->count() === 0) {
                        continue;
                    }
                    $pubDateAttr = $articlePubDate->attr('content');

                    $hashPreview = $this->previewHelper->getOgImageUrlHash($html);
                    $publicationDate = $this->createDateFromString($pubDateAttr);

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
