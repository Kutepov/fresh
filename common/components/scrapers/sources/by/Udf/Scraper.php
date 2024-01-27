<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\by\Udf;

use common\components\scrapers\common\ArticleBodyScraper;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\NewsCutter;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\BaseUrls;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\XPathParserV2;
use common\components\scrapers\common\symfony\SiteParserModule\Infrastructure\SelectorsRemover;
use common\components\scrapers\common\Config;
use common\components\scrapers\dto\ArticleItem;
use GuzzleHttp\Promise\Coroutine;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Class Scraper
 * @package common\components\scrapers\sources\by\Udf
 *
 * @Config (
 * timezone="Europe/Minsk", urls={
 * "https://udf.by/news/covid19/",
 * "https://udf.by/news/economic",
 * "https://udf.by/news/politic",
 * "https://udf.by/news/society",
 * "https://udf.by/news/world",
 * "https://udf.by/news/tech",
 * "https://udf.by/news/nopolitic",
 * "https://udf.by/news/auto/",
 * "https://udf.by/news/health/"
 * })
 */
class Scraper extends \common\components\scrapers\common\Scraper implements ArticleBodyScraper
{
    private const OVERRIDE_REPLACE_TAGS = [
        'b' => [
            [
                'valueType' => 'text',
                'elementName' => 'paragraph',
            ],
        ]
    ];

    private const MONTHS = [
      'январь' => 1,
      'февраль' => 2,
      'март' => 3,
      'апрель' => 4,
      'май' => 5,
      'июнь' => 6,
      'июль' => 7,
      'август' => 8,
      'сентябрь' => 9,
      'октябрь' => 10,
      'ноябрь' => 11,
      'декабрь' => 12
    ];

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

    /** @var BaseUrls */
    private $baseUrls;

    public function __construct(
        SelectorsRemover $selectorsRemover,
        XPathParserV2 $XPathParserV2,
        NewsCutter $newsCutter,
        BaseUrls $baseUrls,
        $config = []
    )
    {
        $this->selectorsRemover = $selectorsRemover;
        $this->XPathParser = $XPathParserV2;
        $this->newsCutter = $newsCutter;
        $this->baseUrls = $baseUrls;

        parent::__construct($config);
    }


    public function parseArticleBody(string $url): PromiseInterface
    {
        return $this->sendAsyncRequestWithProxy(new Request('GET', $url))->then(function (ResponseInterface $response) {
            $raplaceTags = self::OVERRIDE_REPLACE_TAGS;
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');


            $selector = "//article";

            $textNode = $html->filterXPath($selector);

            $this->selectorsRemover->remove(
                "
            //div[contains(@class, 'b-inject')]|
            //div[@class='wrap']|
            //script|
            //div[@class='extra_full']|
            //div[@class='article_list']|
            //div[@class='d55']|
            //div[@class='author']|
            //div[@class='pt10']|
            //span[contains(text(), 'Фото:')]|
            //div[contains(@id, 'adfox_')]
            ",
                $textNode
            );

            $newsLinks = $textNode->filterXPath('//a');
            $this->newsCutter->cutterByLinks($newsLinks);

            $text = $textNode->filterXPath(
                "
            //div[@class='quote' and not(descendant::br)]|
            //img|
            //p|
            //b|
            //ul|
            //ol|
            //h3|
            //br/following-sibling::text()|
            "
            );

            $this->baseUrls->addImageUrl('https://udf.by/');

            return $this->XPathParser->parse($text, $raplaceTags, $this->baseUrls, true);
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//div[@id='content1']";
            $articlesNode = $html->filterXPath($selector);

            $articles = $articlesNode->filterXPath("//div[@class='article1']|//li");

            $lastAddedPublicationTime = $this->lastPublicationTime;

            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    $linkNode = $node->filterXPath("//a[2]")->first();
                    $pageLink = $linkNode->attr('href');
                    $title = $linkNode->text();

                    $dateString = $this->prepareDateString($node->filterXPath("//div[@class='author']")->first()->text());
                    $publicationDate = $this->createDateFromString($dateString);

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

    private function prepareDateString(string $string): string
    {
        $string = trim(explode('|', $string)[0]);
        [$date, $time] = mb_split(',', $string);
        [$day, $month, $year] = mb_split(' ', $date);
        $month = self::MONTHS[$month];
        return $day.'-'.$month.'-'.$year.$time;

    }
}
