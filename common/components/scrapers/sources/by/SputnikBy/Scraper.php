<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\by\SputnikBy;

use common\components\scrapers\common\ArticleBodyScraper;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\NewsCutter;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\XPathParserV2;
use common\components\scrapers\common\symfony\SiteParserModule\Infrastructure\SelectorsRemover;
use common\components\scrapers\common\services\HashImageService;
use common\components\scrapers\common\Config;
use common\components\scrapers\dto\ArticleBody;
use common\components\scrapers\dto\ArticleBodyNode;
use common\components\scrapers\dto\ArticleItem;
use GuzzleHttp\Promise\Coroutine;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Class Scraper
 * @package common\components\scrapers\sources\by\BelSputnikBy
 *
 * @Config (
 * timezone="Europe/Minsk", urls={
 * "https://sputnik.by/world/",
 * "https://sputnik.by/politics/",
 * "https://sputnik.by/economy/",
 * "https://sputnik.by/society/",
 * "https://sputnik.by/health/",
 * "https://sputnik.by/education/",
 * "https://sputnik.by/incidents/",
 * "https://sputnik.by/sport/"
 * })
 */
class Scraper extends \common\components\scrapers\common\Scraper implements ArticleBodyScraper
{
    private const OVERRIDE_REPLACE_TAGS = [
        '#text' => [
            [
                'valueType' => 'text',
                'elementName' => 'paragraph',
            ],
        ],
        'a' => [
            [
                'contains' => 'status',
                'attribute' => 'href',
                'valueType' => 'href',
                'elementName' => 'twitter',
            ],
            [
                'contains' => 'instagram.com',
                'attribute' => 'href',
                'valueType' => 'href',
                'elementName' => 'instagram',
            ],
        ],
        'p' => [
            [
                'valueType' => 'text',
                'elementName' => 'paragraph',
            ],
        ],
        'h1' => [
            [
                'valueType' => 'text',
                'elementName' => 'caption',
            ],
        ],
        'h2' => [
            [
                'valueType' => 'text',
                'elementName' => 'paragraph',
            ],
        ],
        'h3' => [
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
                'valueType' => 'text',
                'elementName' => 'paragraph',
            ],
            [
                'contains' => 'facebook.com',
                'attribute' => 'data-href',
                'valueType' => 'webview',
                'elementName' => 'facebook',
            ],
        ],
        'img' => [
            [
                'valueType' => 'proxyJpg',
                'elementName' => 'image',
                'attribute' => 'data-src',
            ],
            [
                'attribute' => 'src',
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
        'source' => [
            [
                'valueType' => 'video',
                'elementName' => 'video-source',
            ],
        ],
        'iframe' => [
            [
                'contains' => 'youtube.com',
                'attribute' => 'src',
                'valueType' => 'video',
                'elementName' => 'video',
            ],
            [
                'contains' => 'instagram.com',
                'attribute' => 'src',
                'valueType' => 'video',
                'elementName' => 'video',
            ],
            [
                'contains' => 'facebook.com',
                'attribute' => 'src',
                'valueType' => 'video',
                'elementName' => 'facebook',
            ],
            [
                'contains' => 't.me',
                'attribute' => 'src',
                'valueType' => 'src',
                'elementName' => 'telegram',
            ],
            [
                'contains' => 'vk.com',
                'attribute' => 'src',
                'valueType' => 'video',
                'elementName' => 'video',
            ],
            [
                'contains' => 'soundcloud',
                'attribute' => 'src',
                'valueType' => 'webview',
                'elementName' => 'soundcloud',
            ],
        ],
        'amp-img' => [
            [
                'valueType' => 'proxyJpg',
                'elementName' => 'image',
            ],
        ],
        'amp-instagram' => [
            [
                'attribute' => 'data-shortcode',
                'elementName' => 'instagram',
                'valueType' => 'instagram-id',
            ],
        ],
        'amp-youtube' => [
            [
                'attribute' => 'data-videoid',
                'elementName' => 'video',
                'valueType' => 'youtube-video-id',
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
                'valueType' => 'src',
                'elementName' => 'twitter',
            ],
            [
                'valueType' => 'text',
                'elementName' => 'quote',
            ],
        ],
        'link' => [
            [
                'attribute' => 'href',
                'elementName' => 'image',
                'valueType' => 'proxyJpg',
            ],
        ],
        'meta' => [
            [
                'attribute' => 'content',
                'elementName' => 'image',
                'valueType' => 'proxyJpg',
            ],
        ],
        'table' => [
            [
                'elementName' => 'table',
                'valueType' => 'table',
            ],
        ],
        'script' => [
            [
                'attribute' => 'data-telegram-post',
                'elementName' => 'telegram',
                'valueType' => 'telegram',
            ],
        ],

    ];

    /**
     * @var HashImageService
     */
    private $hashImageService;

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
        HashImageService $hashImageService,
        SelectorsRemover $selectorsRemover,
        XPathParserV2 $XPathParserV2,
        NewsCutter $newsCutter,
        $config = []
    )
    {
        $this->hashImageService = $hashImageService;
        $this->selectorsRemover = $selectorsRemover;
        $this->XPathParser = $XPathParserV2;
        $this->newsCutter = $newsCutter;

        parent::__construct($config);
    }


    public function parseArticleBody(string $url): PromiseInterface
    {
        return $this->sendAsyncRequestWithProxy(new Request('GET', $url))->then(function (ResponseInterface $response) {

            $replaceTags = self::OVERRIDE_REPLACE_TAGS;
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');


            $selector = "//div[@class = 'article ']";

            $textNode = $html->filterXPath($selector);

            $this->selectorsRemover->remove(
                "
            //div[contains(@class, 'b-inject')]|
            //strong[contains(text(), 'Читайте также:')]|
            //div[@class='article__google-news']|
            //strong[contains(text(), 'Также на Sputnik:')]|
            //strong[contains(text(), '>>> Хотите еще больше актуальных и интересных новостей – подписывайтесь на')]|
            ",
                $textNode
            );

            $newsLinks = $textNode->filterXPath('//a');
            $this->newsCutter->cutterByLinks($newsLinks);

            $text = $textNode->filterXPath(
                "
            //img|
            //div[@class='article__block']//p|
            //div[@class='article__block']//ul|
            //div[@class='article__block']//ol|
            //div[@class='article__block']//h3|
            //div[@class='article__block']//img|
            //div[@class='article__block']//p|
            //div[@class='article__block']//ul|
            //div[@class='article__block']//ol|
            //div[@class='article__block']//h2|
            //div[@class='article__block']//a[not(contains(@style, 'background:#FFFFFF; line-height:0; '))]|
            //div[@class='article__block']//div[@class='article__text']|
            "
            );

            $result = $this->XPathParser->parse($text, $replaceTags);

            $gallery = $textNode->filterXPath('//div[@class="b-article__media-slider"]//li');

            if ($gallery->count() > 0) {
                $images = [];
                $gallery->each(function (Crawler $node) use (&$images) {
                    $images[] = $this->hashImageService->hashImage($node->attr('data-src'));
                });

                $result->add(new ArticleBodyNode('carousel', $images));
            }

            return $result;
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//div[@class='list list-tag']";
            $articlesNode = $html->filterXPath($selector);

            $articles = $articlesNode->filterXPath('//div[contains(@class, "list__item")]');

            $baseUrl = 'https://sputnik.by';

            $lastAddedPublicationTime = $this->lastPublicationTime;

            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    $linkNode = $node->filterXPath('//a[@class="list__title"]')->first();
                    $pageLink = $baseUrl.$linkNode->attr('href');
                    $title = $linkNode->text();

                    if (stripos($pageLink, 'longrid') !== false) {
                        continue;
                    }

                    $unixTime = $node->filterXPath("//div[contains(@class, 'list__date ')]")->attr('data-unixtime');
                    $dateString = date('d.m.Y H:i:s', (int) $unixTime);
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
}
