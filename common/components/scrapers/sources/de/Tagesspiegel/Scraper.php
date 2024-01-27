<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\de\Tagesspiegel;

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
 * @package common\components\scrapers\sources\de\Tagesspiegel;
 *
 * @Config (
 * timezone="Europe/Berlin", urls={
 * "https://www.tagesspiegel.de/politik/",
 * "https://www.tagesspiegel.de/berlin/",
 * "https://www.tagesspiegel.de/wirtschaft/",
 * "https://www.tagesspiegel.de/gesellschaft/",
 * "https://www.tagesspiegel.de/kultur/",
 * "https://www.tagesspiegel.de/meinung/",
 * "https://www.tagesspiegel.de/sport/",
 * "https://www.tagesspiegel.de/wissen/",
 * "https://www.tagesspiegel.de/verbraucher/"
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
            return $this->XPathParser->parseDescription($html, '//p[@itemprop="description"]');
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//div[@class='ts-content-wrapper hcf-clearfix']";
            $articlesNode = $html->filterXPath($selector);

            $articles = $articlesNode->filterXPath("//li[contains(@class, 'hcf-teaser hcf-left')]|//li[contains(@class, 'hcf-wide hcf-left hcf-teaser')]");

            $lastAddedPublicationTime = $this->lastPublicationTime;
            $lastAddedPublicationTime = $lastAddedPublicationTime->setTimezone($this->timezone);
            $result = [];
            $baseUrl = 'https://www.tagesspiegel.de';
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {

                    if ($node->filterXPath("//svg[@class='ts-marker--paid']")->count()) {
                        continue;
                    }

                    $linkNode = $node->filterXPath("//h2//a")->first();
                    if (!$linkNode->count()) {
                        continue;
                    }

                    $pageLink = $linkNode->attr('href');

                    if (!filter_var($pageLink, FILTER_VALIDATE_URL)) {
                        $pageLink = $baseUrl.$linkNode->attr('href');
                    }


                    $title = $node->filterXPath("//span[@class='hcf-headline']")->first()->text();

                    if ($node->filterXPath("//title[contains(text(), 'SZ Plus')]")->count()) {
                        continue;
                    }

                    $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                    if ($this->isNeedSkipArticle($pageContent)) {
                        continue;
                    }
                    $html = new Crawler();
                    $html->addHtmlContent($pageContent->getBody()->getContents(), 'UTF-8');

                    $dataArticle = $html->filterXPath(
                        "//time[@itemprop='datePublished']")
                        ->first();

                    if (!$dataArticle->count()) {
                        continue;
                    }

                    $pubDateAttr = $dataArticle->attr('datetime');


                    $publicationDate = $this->createDateFromString($pubDateAttr);
                    $hashPreview = $this->previewHelper->getImageUrlHashFromList($node, '//img', 'src', $baseUrl);

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
