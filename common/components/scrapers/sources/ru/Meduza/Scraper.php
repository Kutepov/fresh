<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\ru\Meduza;

use Carbon\Carbon;
use common\components\scrapers\common\ArticleBodyScraper;
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
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\NewsCutter;

/**
 * Class Scraper
 * @package common\components\scrapers\sources\ru\Meduza
 *
 * @Config (
 * timezone="Europe/Moscow", urls={
 * "https://meduza.io/",
 * "https://meduza.io/specials/voina"
 * })
 */
class Scraper extends \common\components\scrapers\common\Scraper implements ArticleBodyScraper
{

    private const REPLACE_TAGS = [
        'img' => [
            [
                'attribute' => 'srcset',
                'valueType' => 'proxyJpg',
                'elementName' => 'image',
            ],
            [
                'attribute' => 'src',
                'valueType' => 'proxyJpg',
                'elementName' => 'image',
            ],
        ],
        'source' => [
            [
                'attribute' => 'srcset',
                'valueType' => 'proxyJpg',
                'elementName' => 'image',
            ],
        ],
        'figcaption' => [
            [
                'valueType' => 'text',
                'elementName' => 'paragraph',
            ]
        ],
        'iframe' => [
            [
                'attribute' => 'src',
                'contains' => 'maphub',
                'valueType' => 'src',
                'elementName' => 'map'
            ],
            [
                'attribute' => 'src',
                'contains' => 'vk.com/widget_post',
                'valueType' => 'src',
                'elementName' => 'vk'
            ],
            [
                'attribute' => 'src',
                'contains' => 'videoembed',
                'valueType' => 'src',
                'elementName' => 'video'
            ]
        ]
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
     * @var XPathParserV2
     */
    private $XPathParser;

    /**
     * @var SelectorsRemover
     */
    private $selectorsRemover;

    /**
     * @var BaseUrls
     */
    private $baseUrls;

    public function __construct(
        NewsCutter       $newsCutter,
        SelectorsRemover $selectorsRemover,
        XPathParserV2    $XPathParser,
        BaseUrls         $baseUrls,
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
        return $this->sendAsyncRequestWithProxy(new Request('GET', $url))->then(function (ResponseInterface $response) {
            $html = new Crawler($response->getBody()->getContents());

            $selector = "//div[@class='GeneralMaterial-article']";

            $textNode = $html->filterXPath($selector);

            $this->selectorsRemover->remove(
                '
                //div[@data-testid="announcement-in-text"]|
                //*[@data-testid="related-rich-block"]|
                //div[data-testid="material-note"]|
                //div[@data-testid="toolbar"]|
                //div[@data-testid="donates-teaser"]|
                //p[contains(text(), "Нам нужна ваша помощь. Пожалуйста")]|
                //p[contains(@class, "MaterialNote-module_note_caption_")]|
                //div[@data-testid="related-block"]|
            ',
                $textNode
            );

            $newsLinks = $textNode->filterXPath('//a');
            $this->newsCutter->cutterByLinks($newsLinks);

            $text = $textNode->filterXPath(
                "
            //img|
            //p|
            //ul|
            //ol|
            //script[contains(@src, 'telegram')]|
            //h2|
            //h3|
            //iframe|
            //blockquote|
            //figure//source[1]|
            //div[contains(@class, 'MediaCaption-module_caption_')]|
            //div[contains(@class, 'MediaCaption-module_credit_')]|
            //div[@data-testid='chapter-block']|
            "
            );

            $this->baseUrls->addImageUrl('https://meduza.io');

            $result = $this->XPathParser->parse($text, self::REPLACE_TAGS, $this->baseUrls);

            return $result;
        });
    }


    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));

            $html = new Crawler($response->getBody()->getContents());

            $selector = "//div[@id='root']";
            $articlesNode = $html->filterXPath($selector);

            $this->selectorsRemover->remove('//div[@data-testid="tag"]', $articlesNode);

            $articles = $articlesNode->filterXPath("//article");

            $lastAddedPublicationTime = $this->lastPublicationTime;
            $result = [];
            $baseUrl = 'https://meduza.io/';
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    $linkNode = $node->filterXPath('//a');
                    if (!$linkNode->count()) {
                        continue;
                    }

                    $pageLink = $baseUrl . $linkNode->attr('href');

                    if (stripos($pageLink, '/live/') !== false) {
                        continue;
                    }

                    $title = $linkNode->text();

                    $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                    if ($this->isNeedSkipArticle($pageContent)) {
                        continue;
                    }

                    $html = new Crawler($pageContent->getBody()->getContents());

                    $articlePubDate = $html->filterXPath("//div[@data-testid='meta-item']//time")->text();
                    $publicationDate = $this->parseDate($articlePubDate);

                    if ($publicationDate > $lastAddedPublicationTime) {
                        $immutableDate = \DateTimeImmutable::createFromMutable($publicationDate);

                        $result[] = new ArticleItem($pageLink, $title, $immutableDate);
                    }
                } catch (\Throwable $exception) {
                    $this->logArticleItemException($exception, $pageLink);
                }
            }


            yield $result;
        });
    }

    private function parseDate(string $dateString): Carbon
    {
        [$time, $date] = explode(', ', $dateString);
        [$day, $month, $year] = explode(' ', $date);

        return $this->createDateFromString($time . ' ' . $day . self::MONTHS[$month] . $year);
    }
}
