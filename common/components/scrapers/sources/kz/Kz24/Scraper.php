<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\kz\Kz24;

use common\components\scrapers\common\ArticleBodyScraper;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\BaseUrls;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\XPathParserV2;
use common\components\scrapers\common\symfony\SiteParserModule\Infrastructure\SelectorsRemover;
use common\components\scrapers\common\Config;
use common\components\scrapers\dto\ArticleItem;
use GuzzleHttp\Promise\Coroutine;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use Symfony\Component\DomCrawler\Crawler;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\NewsCutter;

/**
 * Class Scraper
 * @package common\components\scrapers\sources\kz\Kz24
 *
 * @Config (
 * timezone="Asia/Almaty", urls={
 * "https://24.kz/ru/news/in-the-world",
 * "https://24.kz/ru/news/top-news",
 * "https://24.kz/ru/news/delovye-novosti",
 * "https://24.kz/ru/news/evrovidenie",
 * "https://24.kz/ru/news/zakonoproekt",
 * "https://24.kz/ru/news/culture",
 * "https://24.kz/ru/news/novosti-kazakhstana",
 * "https://24.kz/ru/news/social",
 * "https://24.kz/ru/news/obrazovanie-i-nauka",
 * "https://24.kz/ru/news/obzor-pressy",
 * "https://24.kz/ru/news/policy",
 * "https://24.kz/ru/news/economyc",
 * "https://24.kz/ru/news/pokupaj-kazakhstanskoe",
 * "https://24.kz/ru/news/sport",
 * "https://24.kz/ru/news/poslanie-2020",
 * "https://24.kz/ru/news/polezno-znat",
 * "https://24.kz/ru/news/plan-natsii",
 * "https://24.kz/ru/news/incidents"
 * })
 */
class Scraper extends \common\components\scrapers\common\Scraper implements ArticleBodyScraper
{
    private const OVERRIDE_REPLACE_TAGS = [];

    /**
     * @var NewsCutter
     */
    private $newsCutter;

    /**
     * @var XPathParserV2
     */
    private $XPathParser;

    /**
     * @var BaseUrls
     */
    private $baseUrls;

    /**
     * @var SelectorsRemover
     */
    private $selectorsRemover;

    public function __construct(
        NewsCutter $newsCutter,
        SelectorsRemover $selectorsRemover,
        XPathParserV2 $XPathParser,
        BaseUrls $baseUrls,
        $config = []
    )
    {
        $this->selectorsRemover = $selectorsRemover;
        $this->newsCutter = $newsCutter;
        $this->XPathParser = $XPathParser;
        $this->baseUrls = $baseUrls;

        parent::__construct($config);
    }

    public function parseArticleBody(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));

            $raplaceTags = self::OVERRIDE_REPLACE_TAGS;

            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//article[contains(@class, 'view-article')]";

            $textNode = $html->filterXPath($selector);

            $this->selectorsRemover->remove(
                "
            //div[contains(@class, 'itemBody')]//blockquote[contains(@class, 'instagram-media')]//text()|
            //div[contains(@class, 'itemBody')]//blockquote[contains(@class, 'twitter-tweet')]//text()|
            ",
                $textNode
            );

            $newsLinks = $textNode->filterXPath("//div[contains(@class, 'itemBody')]//a");
            $this->newsCutter->cutterByLinks($newsLinks);

            $text = $textNode->filterXPath(
                "
            //div[contains(@class, 'itemheader')]//a[contains(@class, 'itemImage')]//img|
            //div[contains(@class, 'itemBody')]//img|
            //div[contains(@class, 'itemBody')]//p[not(ancestor::blockquote)]|
            //div[contains(@class, 'itemBody')]//ul|
            //div[contains(@class, 'itemBody')]//ol|
            //div[contains(@class, 'itemBody')]//blockquote[contains(@class, 'instagram-media')]|
            //div[contains(@class, 'itemBody')]//blockquote[contains(@class, 'twitter-tweet')]//a|
            //iframe|
            "
            );

            $this->baseUrls->addImageUrl('https://24.kz');
            $imageNodes = $textNode->filterXPath(
                "
            //div[contains(@class, 'itemheader')]//a[contains(@class, 'itemImage')]//img|
            //div[contains(@class, 'itemBody')]//img|
            "
            );
            $isNeedPrviewImg = !$imageNodes->count();

            $result = $this->XPathParser->parse($text, $raplaceTags, $this->baseUrls, $isNeedPrviewImg);

            yield $result;
        });
    }


    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));

            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//body";
            $articlesNode = $html->filterXPath($selector)->first();

            $baseUrl = 'https://24.kz';

            $articles = $articlesNode->filterXPath("//div[contains(@class, 'itemContainer')]");
            $lastAddedPublicationTime = $this->lastPublicationTime;
            $result = [];


            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    $linkNode = $node->filterXPath("//h2//a");
                    $pageLink = $linkNode->attr('href');
                    $title = $linkNode->text();

                    $pageLink = $baseUrl.$pageLink;

                    $articlePubDate = $node->filterXPath("//time")->first();
                    $pubDateAttr = $articlePubDate->attr('datetime');
                    if (!$pubDateAttr) {
                        continue;
                    }

                    $publicationDate = $this->createDateFromString($pubDateAttr);

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
}
