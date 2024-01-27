<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\ru\TopNews;

use Carbon\Carbon;
use common\components\scrapers\common\ArticleBodyScraper;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\BaseUrls;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\CatchExeptionalParser;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\XPathParserV2;
use common\components\scrapers\common\symfony\SiteParserModule\Infrastructure\SelectorsRemover;
use common\components\scrapers\common\services\HashImageService;
use common\components\scrapers\common\Config;
use common\components\scrapers\dto\ArticleBodyNode;
use common\components\scrapers\dto\ArticleItem;
use GuzzleHttp\Promise\Coroutine;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DomCrawler\Crawler;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\NewsCutter;

/**
 * Class Scraper
 * @package common\components\scrapers\sources\ru\TopNews
 *
 * @Config (
 * timezone="Europe/Moscow", urls={
 * "https://www.topnews.ru/news_1.html",
 * "https://www.topnews.ru/news_cat_policy_1.html",
 * "https://www.topnews.ru/news_cat_economy_1.html",
 * "https://www.topnews.ru/news_cat_show-business_1.html",
 * "https://www.topnews.ru/news_cat_science-tech_1.html",
 * "https://www.topnews.ru/news_cat_sports_1.html",
 * "https://www.topnews.ru/news_cat_incidents_1.html",
 * "https://www.topnews.ru/news_cat_goods-services_1.html",
 * "https://www.topnews.ru/news_cat_kaleidoscope_1.html",
 * "https://www.topnews.ru/news_cat_auto_1.html",
 * "https://www.topnews.ru/news_cat_health_1.html"
 * })
 */
class Scraper extends \common\components\scrapers\common\Scraper implements ArticleBodyScraper
{
    private const OVERRIDE_REPLACE_TAGS = [
        'iframe' => [
            [
                'contains' => 'ridus',
                'attribute' => 'src',
                'valueType' => 'src',
                'elementName' => 'video',
            ],
            [
                'contains' => 'ntv.ru',
                'attribute' => 'src',
                'valueType' => 'src',
                'elementName' => 'video',
            ],
        ],
    ];


    private const MONTHS = [
        'января' => '-01-',
        'февраля' => '-02-',
        'марта' => '-03-',
        'апреля' => '-04-',
        'мая' => '-05-',
        'июня' => '-06-',
        'июля' => '-07-',
        'августа' => '-08-',
        'сентября' => '-09-',
        'октября' => '-10-',
        'ноября' => '-11-',
        'декабря' => '-12-',
    ];


    /**
     * @var NewsCutter
     */
    private $newsCutter;


    /**
     * @var HashImageService
     */
    private $hashImageService;

    /**
     * @var XPathParserV2
     */
    private $XPathParser;

    /**
     * @var CatchExeptionalParser
     */
    private $catchExeptionalParser;


    /**
     * @var SelectorsRemover
     */
    private $selectorsRemover;

    public function __construct(
        HashImageService $hashImageService,
        NewsCutter $newsCutter,
        SelectorsRemover $selectorsRemover,
        XPathParserV2 $XPathParser,
        CatchExeptionalParser $catchExeptionalParser,
        $config = []
    )
    {
        $this->hashImageService = $hashImageService;
        $this->selectorsRemover = $selectorsRemover;
        $this->newsCutter = $newsCutter;
        $this->XPathParser = $XPathParser;
        $this->catchExeptionalParser = $catchExeptionalParser;

        parent::__construct($config);
    }


    public function parseArticleBody(string $url): PromiseInterface
    {
        return $this->sendAsyncRequestWithProxy(new Request('GET', $url))->then(function (ResponseInterface $response) {

            $raplaceTags = self::OVERRIDE_REPLACE_TAGS;

            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//div[contains(@class, 'article-wrap')]";

            $textNode = $html->filterXPath($selector);

            $this->catchExeptionalParser->catchexeption($textNode->filterXPath('//a'));

            $this->selectorsRemover->remove(
                "
            //blockquote[contains(@class, 'twitter-tweet')]//text()|
            //blockquote[contains(@class, 'instagram-media')]//text()|
            //div[contains(@class, 'promo-content')]|
            //div[contains(@class, 'under_articles_for_this')]|
            //noscript
            ",
                $textNode
            );

            $newsLinks = $textNode->filterXPath('//a');
            $this->newsCutter->cutterByLinks($newsLinks);

            $text = $textNode->filterXPath(
                "
            //div[contains(@class, 'img-title-wrap')]//img|
            //p[not(ancestor::blockquote)]|
            //img|
            //blockquote[not(contains(@class, 'twitter-tweet'))]|
            //blockquote[not(contains(@class, 'instagram-media'))]//a|
            //iframe|
            //video|
            //script[@data-telegram-post]|
            //ul|//ol|
            "
            );

            $result = $this->XPathParser->parse($text, $raplaceTags);

            return $result;
        });
    }


    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));

            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents());

            $selector = "//div[contains(@class, 'main-rubric-wrap')]";
            $articlesNode = $html->filterXPath($selector);

            $articles = $articlesNode->filterXPath("//div[contains(@class, 'quotes-wrap')]//div[contains(@class, 'section-topnews-item')]");

            $lastAddedPublicationTime = $this->lastPublicationTime;

            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    $linkNode = $node->filterXPath('//a');
                    $pageLink = $linkNode->attr('href');
                    $title = $node->filterXPath("//div[@class='section-topnews-title']")->first()->text();

                    $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                    if ($this->isNeedSkipArticle($pageContent)) {
                        continue;
                    }
                    $html = new Crawler();
                    $html->addHtmlContent($pageContent->getBody()->getContents(), 'UTF-8');

                    $articlePubDate = $html->filterXPath("//div[@class='tag-date']")->first();
                    $pubDateAttr = $articlePubDate->text();

                    if (!$pubDateAttr) {
                        continue;
                    }
                    $publicationDate = $this->prepareTime($pubDateAttr);

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


    private function prepareTime(string $rawDatetime): Carbon
    {
        [$day, $month, $year, $time] = mb_split(' ', mb_split('|', $rawDatetime)[0]);

        return $this->createDateFromString(trim($year, ', ') . self::MONTHS[$month] . $day . ' ' . $time);
    }

}
