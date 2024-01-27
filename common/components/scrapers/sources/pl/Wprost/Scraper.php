<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\pl\Wprost;

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
 * @package common\components\scrapers\sources\pl\Wprost
 *
 * @Config (
 * timezone="Europe/Warsaw", urls={
 * "https://www.wprost.pl/wiadomosci",
 * "https://www.wprost.pl/polityka",
 * "https://biznes.wprost.pl/",
 * "https://www.wprost.pl/prime-time",
 * "https://zdrowie.wprost.pl/",
 * "https://eko.wprost.pl/"
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
            return $this->XPathParser->parseDescription($html, '//div[@class="art-lead"][1]');
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();

            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//ul[@class='box-list box-list-default  box-list-rwd-bh-lelem-none  disabled-select']|//ul[@class='box-list box-list-rows-lead disabled-select']|//ul[@class='box-list box-list-rows-large disabled-select']";
            $articlesNode = $html->filterXPath($selector);

            $articles = $articlesNode->filterXPath("//li[contains(@class, 'box-list-item box-list-item-type-row box-list-item-')]|//li[@class='box-list-item size-1x1 box-list-item-rwd-row ']");

            $lastAddedPublicationTime = $this->lastPublicationTime;

            $baseUrl = "https://www.wprost.pl";

            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {

                    if ($node->filterXPath("//div[@class='news-containers']//span[@class='item-containers']//span[@title='Premium']")->count()) {
                        continue;
                    }

                    $pageLink = $node->filterXPath("//div[@class='news-titlelead-wrapper']//a|//a[@class='news-title']")->first()->attr('href');
                    if (!filter_var($pageLink, FILTER_VALIDATE_URL)) {
                        $pageLink = $baseUrl . $pageLink;
                    }
                    $title = $node->filterXPath("//div[@class='news-titlelead-wrapper']//strong//span|//strong//span")->first()->text();

                    $innerPageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                    if ($this->isNeedSkipArticle($innerPageContent)) {
                        continue;
                    }
                    $html = new Crawler();
                    $html->addHtmlContent($innerPageContent->getBody()->getContents(), 'UTF-8');

                    $pubDateAttr = $html->filterXPath("//time")->first()->attr('datetime');
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
}
