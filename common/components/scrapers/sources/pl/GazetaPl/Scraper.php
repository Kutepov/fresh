<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\pl\GazetaPl;

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
 * @package common\components\scrapers\sources\pl\GazetaPl
 *
 * @Config (
 * timezone="Europe/Warsaw", urls={
 * "https://www.edziecko.pl/edziecko/0,0.html",
 * "https://kobieta.gazeta.pl/kobieta/0,0.html",
 * "https://kultura.gazeta.pl/kultura/0,0.html",
 * "https://next.gazeta.pl/next/0,0.html",
 * "https://wiadomosci.gazeta.pl/wiadomosci/0,0.html"
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
            $html->addHtmlContent($response->getBody()->getContents(), 'ISO-8859-2');
            return $this->XPathParser->parseDescription($html, '//div[@id="gazeta_article_lead"][1]');
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'ISO-8859-2');

            $selector = "//div[contains(@class, 'body')]";
            $articlesNode = $html->filterXPath($selector);
            $articles = $articlesNode->filterXPath("//li[contains(@class, 'entry')]");
            $lastAddedPublicationTime = $this->lastPublicationTime;

            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    $linkNode = $node->filterXPath('//a')->first();
                    if (!$linkNode->count()) {
                        continue;
                    }
                    $pageLink = $linkNode->attr('href');
                    $dateTimeNode = $node->filterXPath('//time')->first();
                    if ($dateTimeNode->count()) {
                        $pubDateAttr = $dateTimeNode->attr('datetime');
                        $hashPreview = null;
                    }
                    else {
                        $innerPageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                        if ($this->isNeedSkipArticle($innerPageContent)) {
                            continue;
                        }
                        $html = new Crawler();
                        $html->addHtmlContent($innerPageContent->getBody()->getContents(), 'ISO-8859-2');
                        $article = $html->filterXPath("//section[@id = 'article_wrapper']")->first();
                        $dateTimeNode = $article->filterXPath("//div[contains(@class, 'top_section')]//span[contains(@class, 'article_date')]//time");
                        if (!$dateTimeNode->count()) {
                            continue;
                        }
                        $pubDateAttr = $dateTimeNode->attr('datetime');
                        $hashPreview = $this->previewHelper->getOgImageUrlHash($html, 'ZASLEPKA');
                    }

                    $publicationDate = $this->createDateFromString($pubDateAttr);

                    if ($publicationDate > $lastAddedPublicationTime) {
                        $title = $node->filterXPath('//header')->text();
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
