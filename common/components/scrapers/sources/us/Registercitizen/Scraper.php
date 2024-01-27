<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\us\Registercitizen;

use common\components\guzzle\Guzzle;
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
 * @package common\components\scrapers\sources\us\Registercitizen
 *
 * @Config (timezone="America/New_York", urls={
 * "https://www.registercitizen.com/news/",
 * "https://www.registercitizen.com/sports/",
 * "https://www.registercitizen.com/business/",
 * "https://www.registercitizen.com/opinion/",
 * "https://www.registercitizen.com/entertainment/",
 * "https://www.registercitizen.com/lifestyle/"
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

            $selector = "//main[@id='content']";
            $articlesNode = $html->filterXPath($selector)->first();

            $articles = $articlesNode->filterXPath("//li[@class='dynamicSpotlight--item-group']|//li[@class='dynamic-river__item']");

            $lastAddedPublicationTime = $this->lastPublicationTime;
            $baseUrl = 'https://www.registercitizen.com';

            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    $linkNode = $node->filterXPath('//a[contains(@class, "dynamicSpotlight--item-header")]|//a[contains(@class, "dynamic-river__headline")]')->first();
                    if ($linkNode->count() === 0) {
                        continue;
                    }
                    $pageLink = $baseUrl . $linkNode->attr('href');

                    $title = $linkNode->text();

                    $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                    if ($this->isNeedSkipArticle($pageContent)) {
                        continue;
                    }
                    $html = new Crawler($pageContent->getBody()->getContents());

                    $dataArticle = $html->filterXPath("//meta[@property='article:published_time']")->first();

                    if (!$dataArticle->count()) {
                        continue;
                    }

                    $publicationDate = $this->createDateFromString($dataArticle->attr('content'));
                    $hashPreview = $this->previewHelper->getImageUrlHashFromList($node, '//img', 'data-src');

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