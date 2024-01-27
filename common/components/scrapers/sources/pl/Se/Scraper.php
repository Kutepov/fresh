<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\pl\Se;

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
 * @package common\components\scrapers\sources\pl\Se
 *
 * @Config (
 * timezone="Europe/Warsaw", urls={
 * "https://www.se.pl/wiadomosci/",
 * "https://www.se.pl/rozrywka/",
 * "https://superbiz.se.pl/",
 * "https://superseriale.se.pl/",
 * "https://sport.se.pl/",
 * "https://www.se.pl/auto/",
 * "https://www.se.pl/styl-zycia/",
 * "https://www.se.pl/rozrywka/quizy/",
 * "https://polityka.se.pl/"
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
            return $this->XPathParser->parseDescription($html, '//p[@class="lead"][1]');
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();

            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//div[@id='hook_content']";
            $articlesNode = $html->filterXPath($selector);

            $articles = $articlesNode->filterXPath("//div[contains(@class, 'element   type--article')]");
            $lastAddedPublicationTime = $this->lastPublicationTime;

            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    $pageLink = $node->filterXPath("//div[@class='element__content']//a")->first()->attr('href');
                    $title = $node->filterXPath("//div[@class='element__content']//a")->first()->text();


                    $innerPageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                    if ($this->isNeedSkipArticle($innerPageContent)) {
                        continue;
                    }
                    $html = new Crawler();
                    $html->addHtmlContent($innerPageContent->getBody()->getContents(), 'UTF-8');

                    $pubDateAttr = $html->filterXPath("//span[@id='timezone']")->first()->text();


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
