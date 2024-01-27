<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\ua\Gordon\uk;

use Carbon\Carbon;
use common\components\guzzle\Guzzle;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\BaseUrls;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\XPathParserV2;
use common\components\scrapers\common\services\HashImageService;
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
 * @package common\components\scrapers\sources\ua\Gordon\uk
 *
 * @Config (timezone="Europe/Kiev", urls={
"https://gordonua.com/ukr/bulvar.html",
"https://gordonua.com/ukr/interview.html",
"https://gordonua.com/ukr/news.html",
"https://gordonua.com/ukr/news/culture.html",
"https://gordonua.com/ukr/news/health.html",
"https://gordonua.com/ukr/news/money.html",
"https://gordonua.com/ukr/news/politics.html",
"https://gordonua.com/ukr/news/science.html",
"https://gordonua.com/ukr/news/sport.html",
"https://gordonua.com/ukr/news/war.html",
"https://gordonua.com/ukr/news/worldnews.html"
 * })
 */
class Scraper extends \common\components\scrapers\common\Scraper implements ArticleBodyScraper
{

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

    private const MONTHS_UK = [
        'січня' => 1,
        'лютого' => 2,
        'березня' => 3,
        'квітня' => 4,
        'травня' => 5,
        'червня' => 6,
        'липня' => 7,
        'серпня' => 8,
        'вересня' => 9,
        'жовтня' => 10,
        'листопада' => 11,
        'грудня' => 12
    ];

    private const REPLACE_TAGS = [
        'a' => [
            [
                'contains' => 'status',
                'attribute' => 'href',
                'valueType' => 'href',
                'elementName' => 'twitter',
            ],
            [
                'contains' => 'facebook.com',
                'attribute' => 'href',
                'valueType' => 'href',
                'elementName' => 'facebook',
            ],
            [
                'contains' => 't.me',
                'attribute' => 'href',
                'valueType' => 'href',
                'elementName' => 'telegram',
            ],
        ],
        'p' => [
            [
                'valueType' => 'text',
                'elementName' => 'paragraph',
            ],
        ],
        'h2' => [
            [
                'valueType' => 'text',
                'elementName' => 'paragraph',
            ],
        ],
        'ol' => [
            [
                'valueType' => 'ol',
                'elementName' => 'ol',
            ],
        ],
        'ul' => [
            [
                'valueType' => 'ul',
                'elementName' => 'ul',
            ],
        ],
        'div' => [
            [
                'contains' => 'facebook.com',
                'attribute' => 'data-href',
                'elementName' => 'facebook',
                'valueType' => 'facebook',
            ],
            [
                'valueType' => 'text',
                'elementName' => 'quote',
            ],
            [
                'contains' => 'special inside',
                'attribute' => 'class',
                'elementName' => 'image',
                'valueType' => 'proxyJpg',
            ],
            [
                'contains' => 'carousel-inner',
                'attribute' => 'class',
                'elementName' => 'carousel',
                'valueType' => 'carousel',
            ],
        ],
        'img' => [
            [
                'attribute' => 'data-src',
                'valueType' => 'proxyJpg',
                'elementName' => 'image',
            ],
        ],
        'video' => [
            [
                'valueType' => 'video',
                'elementName' => 'video',
            ],
        ],
        'iframe' => [
            [
                'contains' => 'youtube.com',
                'attribute' => 'src',
                'valueType' => 'video',
                'elementName' => 'video',
            ],
        ],
        'blockquote' => [
            [
                'contains' => 'instagram.com',
                'attribute' => 'data-instgrm-permalink',
                'valueType' => 'data-instgrm-permalink',
                'elementName' => 'instagram',
            ],
            [
                'contains' => 'twitter-tweet',
                'attribute' => 'class',
                'valueType' => 'cite',
                'elementName' => 'twitter',
            ],
            [
                'valueType' => 'text',
                'elementName' => 'quote',
            ],
        ],
        'script' => [
            [
                'attribute' => 'data-telegram-post',
                'elementName' => 'telegram',
                'valueType' => 'telegram',
            ],
        ],
        'meta' => [
            [
                'attribute' => 'content',
                'elementName' => 'image',
                'valueType' => 'proxyJpg',
            ],
        ],
    ];


    /**
     * @var SelectorsRemover
     */
    private $selectorsRemover;

    /**
     * @var HashImageService
     */
    private $hashImageService;

    /**
     * @var XPathParserV2
     */
    private $XPathParser;

    /**
     * @var BaseUrls
     */
    private $BaseUrls;


    /**
     * @var NewsCutter
     */
    private $newsCutter;

    public function __construct(
        SelectorsRemover $selectorsRemover,
        HashImageService $hashImageService,
        NewsCutter $newsCutter,
        XPathParserV2 $XPathParserV2,
        BaseUrls $baseUrls,
        $config = []
    )
    {
        $this->selectorsRemover = $selectorsRemover;
        $this->hashImageService = $hashImageService;
        $this->newsCutter = $newsCutter;
        $this->XPathParser = $XPathParserV2;
        $this->BaseUrls = $baseUrls;

        parent::__construct($config);
    }

    public function parseArticleBody(string $url): PromiseInterface
    {
        return $this->sendAsyncRequestWithProxy(new Request('GET', $url))->then(function (ResponseInterface $response) {
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//div[contains(@class,'article')]|//body[@class='gospec']";

            $textNode = $html->filterXPath($selector);

            $this->selectorsRemover->remove(
                "
            //p[not(*)][not(normalize-space())]|
            //div[contains(@class, 'a_body')]//blockquote[contains(@class, 'twitter-tweet')]//text()|
            //div[contains(@class, 'a_body')]//blockquote[contains(@class, 'instagram-media')]//text()|
            //div[contains(@class, 'fb-post')]//text()|
            //a[@class='ar_prev2']|//a[@class='ar_next2']|//a[contains(@href, 'instagram.com')]|
            //div[@class='gal_thumbs']|
            //div[@class='footer']|
            //div[@class='lines_image']|
            //ul[@class='gal_nav']|
            //*[contains(text(), 'Фото:')]|
            //*[contains(text(), 'фотогалерея:')]|
            //div[@class='dark_header span-12']|
            //div[@class='carousel_gal_thumbs slide']|
            //span[@class='grey']|
            //script
            ",
                $textNode
            );

            $newsLinks = $textNode->filterXPath("//div[contains(@class, 'a_body')]//a|//div[contains(@class, 'a_description')]//a");

            $this->newsCutter->cutterByLinks($newsLinks);

            $text = $textNode->filterXPath(
                "//div[@class='a_card']//img|
            //div[contains(@class, 'a_description')]|
            //div[contains(@class, 'video')]//iframe|
            //div[contains(@class, 'a_body')]//p|
            //div[contains(@class, 'a_body')]//h2|
            //div[contains(@class, 'a_body')]//img|
            //div[contains(@class, 'a_body')]//script|
            //div[contains(@class, 'a_body')]//iframe|
            //div[contains(@class, 'a_body')]//ul|
            //div[contains(@class, 'a_body')]//div[contains(@class, 'cut')]|
            //div[contains(@class, 'a_body')]//ol|
            //div[contains(@class, 'a_body')]//blockquote//a|
            //div[contains(@class, 'a_body')]//blockquote[@class='instagram-media']|
            //div[contains(@class, 'a_body')]//div[@class='carousel-inner']|
            //div[contains(@class, 'a_card')]//img|
            //div[contains(@class, 'article')]//h2|
            //div[@class='a_card infog']//img|
            //div[@class='special inside']|
            //div[@class='wrap']//h2|
            //div[@class='wrap']//iframe|
            //div[@class='wrap']//p|
            //div[@class='wrap']//h2|
            //div[@class='wrap']//img[not(@class='loadscroll')]|
            //div[@class='wrap']//script|
            //div[@class='wrap']//iframe|
            //div[@class='wrap']//ul|
            //div[@class='wrap']//div[contains(@class, 'cut')]|
            //div[@class='wrap']//ol|
            //div[@class='wrap']//blockquote|
            //div[@class='wrap']//blockquote//a|
            //div[@class='wrap']//img[not(@class='loadscroll')]|
            //div[@class='wrap']//div[@class='carousel-inner']|
            //div[@class='block article fotovideo']//div[@class='carousel-inner']|
            //a[contains(@href, 't.me')]|
            "
            );

            $replaceTags = self::REPLACE_TAGS;
            $this->BaseUrls->addImageUrl('https://gordonua.com');
            $result = $this->XPathParser->parse($text, $replaceTags, $this->BaseUrls, true, null, true, true);

            $description = $this->XPathParser->parseDescription(
                $html,
                '//div[@class="a_card"]//h2|//div[@class="a_card"]//p[1]',
                true,
                $this->XPathParser::DESCRIPTION_TAG_HTML
            )->getNodes()[0]->getValue();

            $result->setDescription($description);

            return $result;
        });
    }

    private function generateHashImage($url)
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (!$scheme) {
            $url = 'https://' . $this->getHost() . $url;
        }
        return $this->hashImageService->hashImage($url);
    }

    private function parseLink(): string
    {
        return 'https://gordonua.com';
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addHtmlContent($pageContent->getBody()->getContents(), 'UTF-8');

            $selector = "//div[@class='row']//div[@class='span-8']";
            $articlesNode = $html->filterXPath($selector)->first();

            $articles = $articlesNode->filterXPath("//div[contains(@class, 'media')]//div[@class='row']");

            $lastAddedPublicationTime = $this->lastPublicationTime;

            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    $pageLink = $node->filterXPath('//div[@class="lenta_head"]//a')->first()->attr('href');
                    if ($pageLink == 'javascript:void(0);') {
                        continue;
                    }
                    if (!filter_var($pageLink, FILTER_VALIDATE_URL)) {
                        $pageLink = $this->parseLink() . $pageLink;
                    }
                    $pubDateAttr = $node->filterXPath("//div[contains(@class, 'for_data')]")->first()->text();
                    $publicationDate = $this->prepareTime($pubDateAttr);

                    if ($publicationDate > $lastAddedPublicationTime) {

                        $title = $node->filterXPath('//div[contains(@class, "lenta_head")]')->first()->text();
                        $title = mb_ereg_replace(' G$', '', $title);

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
        [$date] = mb_split('\|', $rawDatetime);
        [$day, $datetime] = mb_split(' ', $date);
        if (in_array($day, ['Сьогодні', 'Сегодня'])) {
            $day = 0;
        }
        elseif (in_array($day, ['Вчера', 'Учора'])) {
            $day = 1;
        }
        else {
            mb_ereg('\d{4}', $date, $year);
            [$day, $datetime] = mb_split('\, \d{4} ', $date);

            [$dateDay, $dateMonth] = mb_split(' ', $day);
            $dateMonth = self::MONTHS[$dateMonth] ?? self::MONTHS_UK[$dateMonth];

            $today = new \DateTime();
            $date = new \DateTime($year[0] . "-" . $dateMonth . "-" . $dateDay);
            $interval = $today->diff($date);

            $day = $interval->days;

        }
        [$hour, $minute] = mb_split('\.', $datetime);

        $time = new \DateTime();
        $time->modify("-$day days");
        $time->setTime((int)$hour, (int)$minute);
        $time->setDate((int)$time->format('Y'), (int)$time->format('m'), (int)$time->format('d'));

        return $this->createDateFromString($time->format('Y-m-d H:i:s'));
    }

    public function getHost(): string
    {
        return 'gordonua.com';
    }

    public function proxyEnablingAttempt(): ?int
    {
        return Guzzle::PROXY_ALWAYS_ENABLED;
    }
}