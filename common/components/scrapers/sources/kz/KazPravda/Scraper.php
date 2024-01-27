<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\kz\KazPravda;

use Carbon\Carbon;
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
 * @package common\components\scrapers\sources\kz\KazPravda;
 *
 * @Config (
 * timezone="Asia/Almaty", urls={
 * "https://kazpravda.kz/r/prezident/",
 * "https://www.kazpravda.kz/r/politika",
 * "https://www.kazpravda.kz/r/ekonomika",
 * "https://www.kazpravda.kz/r/proisshestviya",
 * "https://www.kazpravda.kz/r/obshchestvo",
 * "https://www.kazpravda.kz/r/tehnologii",
 * "https://www.kazpravda.kz/r/kultura",
 * "https://www.kazpravda.kz/r/sport",
 * "https://www.kazpravda.kz/r/v-mire"
 * })
 */
class Scraper extends \common\components\scrapers\common\Scraper implements ArticleBodyScraper
{
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

    private const OVERRIDE_TAGS = [
        'div' => [
            [
                'valueType' => 'carousel',
                'elementName' => 'carousel',
                'attribute' => 'class',
                'contains' => 'swiper-wrapper js-lg',
                'img-attr' => 'data-src',
            ]
        ],
    ];

    /**
     * @var SelectorsRemover
     */
    private $selectorsRemover;

    /**
     * @var XPathParserV2
     */
    private $XpathParser;

    /**
     * @var NewsCutter
     */
    private $newsCutter;

    /**
     * @var BaseUrls
     */
    private $baseUrls;

    public function __construct(
        SelectorsRemover $selectorsRemover,
        BaseUrls         $baseUrls,
        XPathParserV2    $XpathParser,
        NewsCutter       $newsCutter,
                         $config = []
    )
    {
        $this->selectorsRemover = $selectorsRemover;
        $this->baseUrls = $baseUrls;
        $this->newsCutter = $newsCutter;
        $this->XpathParser = $XpathParser;

        parent::__construct($config);
    }

    public function parseArticleBody(string $url): PromiseInterface
    {
        return $this->sendAsyncRequestWithProxy(new Request('GET', $url))->then(function (ResponseInterface $response) {
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//article[@class='article']";

            $textNode = $html->filterXPath($selector);

            $newsLinks = $textNode->filterXPath('//a');
            $this->newsCutter->cutterByLinks($newsLinks);

            $this->selectorsRemover->remove('
                    //div[@class="article__content"]|
                    //*[contains(text(), "Фото: ")]|
                    //*[text()="Фото автора"]|
                    //div[@class="swiper-wrapper"]|
            ', $textNode);

            $text = $textNode->filterXPath(
                "
            //p[not(ancestor::blockquote)]|
            //blockquote[not(@class='wp-embedded-content')]|
            //h1[not(@class='singular__title')]|
            //img[not(parent::div[@class='photo-report__img'])]|
            //p|
            //figure//img|
            //ul|//ol|
            //iframe|
            //div[contains(concat(' ', @class, ' '), ' singular__content ')]/child::text()[1]|
            //div[@class='swiper-wrapper js-lg']|
            "
            );

            $this->baseUrls->addImageUrl('https://www.kazpravda.kz');

            return $this->XpathParser->parse($text, self::OVERRIDE_TAGS, $this->baseUrls, false, null, false);
        });

    }

    private function generateValidDate(string $date): ?Carbon
    {
        [$date, $time] = explode(' г. ', $date);
        [$day, $month, $year] = explode(' ', $date);
        return $this->createDateFromString($day . self::MONTHS[$month] . $year . ' ' . $time);
    }


    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//main[@class='app__main']";
            $articlesNode = $html->filterXPath($selector)->first();

            $this->selectorsRemover->remove('//div[@class="tab-content"]', $articlesNode);

            $baseLink = 'https://www.kazpravda.kz';

            $articles = $articlesNode->filterXPath("//article");

            dump($url . ' '. $articles->count());

            $lastAddedPublicationTime = $this->lastPublicationTime;
            $lastAddedPublicationTime = $lastAddedPublicationTime->setTimezone($this->timezone);
            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {

                    $title = $node->filterXPath('//div[@class="news-slider__title"]|//div[@class="daynews__title"]|//div[@class="news__title"]')->text();
                    $link = $node->filterXPath('//a');
                    $pageLink = $baseLink . $link->attr('href');

                    $pubDateAttr = $node->filterXPath('//time')->text();

                    $publicationDate = $this->generateValidDate($pubDateAttr);

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
