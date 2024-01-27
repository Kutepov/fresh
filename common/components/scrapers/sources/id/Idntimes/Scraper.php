<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\id\Idntimes;

use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\XPathParserV2;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\NewsCutter;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\SelectorsRemover;
use common\components\scrapers\common\ArticleBodyScraper;
use common\components\scrapers\dto\ArticleItem;
use GuzzleHttp\Promise\Coroutine;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DomCrawler\Crawler;
use common\components\scrapers\common\Config;

/**
 * Class Scraper
 * @package common\components\scrapers\sources\id\Idntimes
 *
 * @Config (timezone="Asia/Jakarta", urls={
 * "https://www.idntimes.com/news",
 * "https://www.idntimes.com/business/",
 * "https://www.idntimes.com/sport",
 * "https://www.idntimes.com/tech",
 * "https://www.idntimes.com/hype",
 * "https://www.idntimes.com/life",
 * "https://www.idntimes.com/health",
 * "https://www.idntimes.com/travel"
 * })
 */
class Scraper extends \common\components\scrapers\common\Scraper implements ArticleBodyScraper
{

    public const REPLACE_TAGS = [];

    /**
     * @var SelectorsRemover
     */
    private $selectorsRemover;

    /**
     * @var XPathParserV2
     */
    private $XPathParser;

    /**
     * @var NewsCutter
     */
    private $newsCutter;

    public function __construct(
        SelectorsRemover $selectorsRemover,
        NewsCutter $newsCutter,
        XPathParserV2 $XPathParserV2,
        $config = []
    )
    {
        $this->selectorsRemover = $selectorsRemover;
        $this->newsCutter = $newsCutter;
        $this->XPathParser = $XPathParserV2;

        parent::__construct($config);
    }

    public function parseArticleBody(string $url): PromiseInterface
    {
        return $this->sendAsyncRequestWithProxy(new Request('GET', $url))->then(function (ResponseInterface $response) {
            $html = new Crawler();
            $html->addHtmlContent($this->clearHtml($response->getBody()->getContents()), 'UTF-8');

            $selector = "//section[@class='content-post clearfix']";

            $textNode = $html->filterXPath($selector)->first();

            $this->selectorsRemover->remove(
                "//div[contains(@class, 'author divide-table')]|
                //div[@class='share divide-table']|
                //div[@class='content-post-topic']|
                //div[@class='content-post-editorial']|
                //div[@class='content-post-comment']|
                //section[@class='latest-post clearfix']|
                //div[@class='community-author-bottom']|
                //p[@class='article-disclaimer']",
                $textNode
            );

            $newsLinks = $textNode->filterXPath("//a");
            $this->newsCutter->cutterByLinks($newsLinks);

            $text = $textNode->filterXPath(
                "
            //img|
            //p|
            //ul|
            //ol|
            //a|
            //h3
"
            );

            $imageNodes = $textNode->filterXPath('//img');
            $isNeedPrviewImg = !$imageNodes->count();

            return $this->XPathParser->parse($text, null, null, $isNeedPrviewImg);
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addHtmlContent($this->clearHtml($pageContent->getBody()->getContents()), 'UTF-8');

            $selector = "//div[@id='latest-article']";

            $articlesNode = $html->children('body')->filterXPath($selector)->first();

            $articles = $articlesNode->filterXPath("//div[contains(@class, 'box-list')]");

            $lastAddedPublicationTime = $this->lastPublicationTime;

            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    $linkNode = $node->filterXPath("//div[contains(@class, 'box-description')]/a")->first();
                    $pageLink = $linkNode->attr('href');
                    $title = $linkNode->filterXPath('//h2')->first()->text();

                    if (stripos($title, '[QUIZ]') !== false) {
                        continue;
                    }

                    $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                    if ($this->isNeedSkipArticle($pageContent)) {
                        continue;
                    }
                    $html = new Crawler();
                    $html->addHtmlContent($this->clearHtml($pageContent->getBody()->getContents()), 'UTF-8');

                    $articleData = json_decode($html->filterXPath("//script[@type = 'application/ld+json']")->eq(1)->first()->text());

                    $articlePubDate = $articleData->datePublished;

                    if (!$articlePubDate) {
                        continue;
                    }

                    $publicationDate = $this->createDateFromString($articlePubDate);
                    if ($publicationDate > $lastAddedPublicationTime) {
                        $result[] = new ArticleItem($pageLink, $title, $publicationDate);
                    }
                } catch (\Throwable $exception) {
                    $this->logArticleItemException($exception, $pageLink);
                }
            }

            yield $result;
        });
    }

    /**
     * @param string $html
     * @return string
     */
    private function clearHtml(string $html): string
    {
        return preg_replace("|/>|", '>', $html, 1);
    }
}