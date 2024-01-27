<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\ca\Torontosun;

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
 * @package common\components\scrapers\sources\ca\Torontosun;
 *
 * @Config (
 * timezone="Canada/Atlantic", urls={
 * "https://torontosun.com/category/news/local-news/",
 * "https://torontosun.com/category/news/provincial/",
 * "https://torontosun.com/category/news/national/",
 * "https://torontosun.com/category/news/world/",
 * "https://torontosun.com/category/news/crime/",
 * "https://torontosun.com/category/news/weird/",
 * "https://torontosun.com/category/business/",
 * "https://torontosun.com/category/technology/",
 * "https://torontosun.com/category/sports/",
 * "https://torontosun.com/category/opinion/",
 * "https://torontosun.com/category/entertainment/",
 * "https://torontosun.com/category/life/"
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
            return $this->XPathParser->parseDescription($html, '//section[@class="article-content__content-group"]//p[1]');
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//main[@id='main-content']";
            $articlesNode = $html->filterXPath($selector);

            $articles = $articlesNode->filterXPath("//article[contains(@class, 'article-card article-card')]");
            $lastAddedPublicationTime = $this->lastPublicationTime;
            $lastAddedPublicationTime = $lastAddedPublicationTime->setTimezone($this->timezone);
            $result = [];
            $baseUrl = 'https://torontosun.com';
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {

                    $linkNode = $node->filterXPath("//a")->first();
                    $pageLink = $linkNode->attr('href');

                    if (!filter_var($pageLink, FILTER_VALIDATE_URL)) {
                        $pageLink = $baseUrl.$pageLink;
                    }

                    $titleNode = $node->filterXPath("//span[@class='article-card__headline-clamp']")->first();
                    if (!$titleNode->count()) {
                        continue;
                    }
                    $title = $titleNode->text();

                    $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                    if ($this->isNeedSkipArticle($pageContent)) {
                        continue;
                    }
                    $html = new Crawler();
                    $html->addHtmlContent($pageContent->getBody()->getContents(), 'UTF-8');

                    $dataArticle = $html->filterXPath("//script[contains(text(), 'datePublished')]")->first();

                    if (!$dataArticle->count()) {
                        continue;
                    }

                    $dataArticle = json_decode($dataArticle->text(), true);
                    if (!isset($dataArticle['datePublished'])) {
                        continue;
                    }

                    $publicationDate = $this->createDateFromString($dataArticle['datePublished']);

                    $hashPreview = $this->previewHelper->getImageUrlHashFromList($node, "//img", 'data-src');

                    if ($publicationDate >= $lastAddedPublicationTime) {
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
