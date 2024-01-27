<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\ru\UraNews;

use Carbon\Carbon;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\XPathParserV2;
use common\components\scrapers\common\symfony\SiteParserModule\Infrastructure\SelectorsRemover;
use common\components\scrapers\common\Config;
use common\components\scrapers\common\ArticleBodyScraper;
use common\components\scrapers\dto\ArticleItem;
use GuzzleHttp\Promise\Coroutine;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DomCrawler\Crawler;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\NewsCutter;

/**
 * Class Scraper
 * @package common\components\scrapers\sources\ru\UraNews
 *
 * @Config (
 * timezone="Europe/Moscow", urls={
 * "https://ura.news/chel",
 * "https://ura.news/khanti",
 * "https://ura.news/kurgan",
 * "https://ura.news/msk",
 * "https://ura.news/perm",
 * "https://ura.news/svrd",
 * "https://ura.news/tumen",
 * "https://ura.news/yamal"
 * })
 */
class Scraper extends \common\components\scrapers\common\Scraper implements ArticleBodyScraper
{
    private const OVERRIDE_REPLACE_TAGS = [];

    private const MONTHS = [
        'января' => 1,
        'февраля' => 2,
        'марта' => 3,
        'апреля' => 4,
        'мая' => 5,
        'июня' => 6,
        'июля' => 7,
        'августа' => 8,
        'сентября' => 9,
        'октября' => 10,
        'ноября' => 11,
        'декабря' => 12,
    ];


    /**
     * @var NewsCutter
     */
    private $newsCutter;

    /**
     * @var XPathParserV2
     */
    private $XPathParser;

    /**
     * @var SelectorsRemover
     */
    private $selectorsRemover;

    public function __construct(
        NewsCutter $newsCutter,
        SelectorsRemover $selectorsRemover,
        XPathParserV2 $XPathParser,
        $config = []
    )
    {
        $this->selectorsRemover = $selectorsRemover;
        $this->newsCutter = $newsCutter;
        $this->XPathParser = $XPathParser;

        parent::__construct($config);
    }

    public function parseArticleBody(string $url): PromiseInterface
    {
        return $this->sendAsyncRequestWithProxy(new Request('GET', $url))->then(function (ResponseInterface $response) use ($url) {
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $raplaceTags = self::OVERRIDE_REPLACE_TAGS;

            $selector = "//div[@class='item-text']";

            $textNode = $html->filterXPath($selector);

            $this->selectorsRemover->remove(
                "
            //p[@class = 'yandex-rss-hidden']|
            //div[contains(@class, 'it-incut')]|
            //div[contains(@class, 'vc-incut-story')]|
            //div[contains(@class, 'item-img-description')]|
            //blockquote[contains(@class, 'instagram-media')]//text()|
            //blockquote[contains(@class, 'twitter-tweet')]//text()|
            //div[contains(@class, 'fb-post')]//text()|
            ",
                $textNode
            );

            $newsLinks = $textNode->filterXPath('//a');
            $this->newsCutter->cutterByLinks($newsLinks);

            $imagesNodes = $textNode->filterXPath('//img');
            $this->newsCutter->cutterNewsWithoutImages($imagesNodes);

            $text = $textNode->filterXPath(
                "
            //img|
            //p|
            //ul|
            //ol|
            //iframe|
            //blockquote[contains(@class, 'instagram-media')]|
            //blockquote[contains(@class, 'twitter-tweet')]//a|
            //div[contains(@class, 'fb-post')]|
            "
            );

            return $this->XPathParser->parse($text, $raplaceTags, null);
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));

            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//div[@id='vc_list_news_all']";
            $articlesNode = $html->filterXPath($selector);

            $dayDiv = $articlesNode->filterXPath("//div[@class='list-scroll-container'][1]|//div[@class='list-scroll-container'][2]");

            $lastAddedPublicationTime = $this->lastPublicationTime;
            $basePath = 'https://ura.news';

            $result = [];

            for ($day = $dayDiv->count() - 1; $day >= 0; --$day) {

                $currentDayDiv = $dayDiv->eq($day);
                $dateText = $currentDayDiv->filterXPath("//div[@class='list-scroll-date']//span")->text();

                $articles = $currentDayDiv->filterXPath("//ul//li");

                for ($i = $articles->count() - 1; $i >= 0; --$i) {
                    $node = $articles->eq($i);
                    try {
                        $linkNode = $node->filterXPath("//a");
                        $pageLink = $linkNode->attr('href');
                        if (!preg_match('#https?://#i', $pageLink)) {
                            $pageLink = $basePath . $pageLink;
                        }

                        $time = $node->filterXPath("//span[@class='time']")->first()->text();

                        $publicationDate = $this->prepareTime($dateText, $time);

                        if ($publicationDate > $lastAddedPublicationTime) {
                            $this->selectorsRemover->remove("//span[@class='time']", $node);
                            $title = $linkNode->text();

                            $result[] = new ArticleItem($pageLink, $title, $publicationDate);
                        }
                    } catch (\Throwable $exception) {
                        $this->logArticleItemException($exception, $pageLink);
                    }
                }
            }
            yield $result;
        });
    }


    private function prepareTime(string $date, string $time)
    {
        $arrayData = explode("\xc2\xa0", $date);

        [$day, $month, $year] = $arrayData;
        [$hour, $minute] = mb_split(':', $time);

        $articleDate = new \DateTime();
        $month = self::MONTHS[$month];
        $articleDate->setDate((int) $year, (int) $month, (int) $day);
        $articleDate->setTime((int) $hour, (int) $minute);

        return $this->createDateFromString($articleDate->format('Y-m-d H:i:s'));
    }

}
