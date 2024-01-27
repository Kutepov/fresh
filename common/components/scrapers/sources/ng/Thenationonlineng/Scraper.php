<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\ng\Thenationonlineng;

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
 * @package common\components\scrapers\sources\ng\Thenationonlineng;
 *
 * @Config (
 * timezone="Africa/Lusaka", urls={
 * "https://thenationonlineng.net/category/business/",
 * "https://thenationonlineng.net/category/columnists/",
 * "https://thenationonlineng.net/category/editorials/",
 * "https://thenationonlineng.net/category/education-news-nigeria/",
 * "https://thenationonlineng.net/category/entertainment/",
 * "https://thenationonlineng.net/category/arts-life/life-the-midweek-magazine/",
 * "https://thenationonlineng.net/category/news/",
 * "https://thenationonlineng.net/category/politics/",
 * "https://thenationonlineng.net/category/online-special/",
 * "https://thenationonlineng.net/category/sports2/sports-news/"
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
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//div[@class='main-wrap content-main-wrap']";
            $articlesNode = $html->filterXPath($selector);

            $articles = $articlesNode->filterXPath("//article[contains(@class, 'type-post format-standard')]");

            $lastAddedPublicationTime = $this->lastPublicationTime;

            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    $linkNode = $node->filterXPath("//h2[@class='title']//a")->first();
                    $pageLink = $linkNode->attr('href');
                    $title = $linkNode->text();

                    if (!$node->filterXPath("//time")->first()->count()) {

                        $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                        if ($this->isNeedSkipArticle($pageContent)) {
                            continue;
                        }
                        $html = new Crawler();
                        $html->addHtmlContent($pageContent->getBody()->getContents(), 'UTF-8');

                        $dateTime = $html->filterXPath("//time")->first()->attr('datetime');
                    }
                    else {
                        $dateTime = $node->filterXPath("//time")->first()->attr('datetime');
                    }


                    $publicationDate = $this->createDateFromString($dateTime);
                    $imgHash = $this->previewHelper->getImageUrlHashFromList($node, "//img", 'data-src');

                    if ($publicationDate >= $lastAddedPublicationTime) {
                        $result[] = new ArticleItem($pageLink, $title, $publicationDate, $imgHash);
                    }
                } catch (\Throwable $exception) {
                    $this->logArticleItemException($exception, $pageLink);
                }
            }

            yield $result;
        });
    }
}
