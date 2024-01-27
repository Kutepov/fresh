<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\ru\Iz;

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
 * @package common\components\scrapers\sources\ru\Iz
 *
 * @Config (
 * timezone="Europe/Moscow", urls={
 * "https://iz.ru/news",
 * "https://iz.ru/rubric/politika",
 * "https://iz.ru/rubric/ekonomika",
 * "https://iz.ru/rubric/obshchestvo",
 * "https://iz.ru/rubric/proisshestviia",
 * "https://iz.ru/rubric/armiia",
 * "https://iz.ru/rubric/kultura",
 * "https://iz.ru/rubric/stil",
 * "https://iz.ru/rubric/auto",
 * "https://iz.ru/rubric/nauka",
 * "https://iz.ru/rubric/sport",
 * "https://iz.ru/rubric/internet",
 * "https://iz.ru/rubric/turizm"
 * })
 */
class Scraper extends \common\components\scrapers\common\Scraper implements ArticleBodyScraper
{

    private const OVERRIDE_REPLACE_TAGS = [
        'iframe' => [
            [
                'contains' => 'iz.ru',
                'attribute' => 'src',
                'valueType' => 'video',
                'elementName' => 'video',
            ],
            [
                'contains' => 'igi-player',
                'attribute' => 'class',
                'valueType' => 'video',
                'elementName' => 'video',
            ],
            [
                'contains' => 'ren.tv',
                'attribute' => 'src',
                'valueType' => 'video',
                'elementName' => 'video',
            ],
        ],
        'h2' => [
            [
                'valueType' => 'text',
                'elementName' => 'caption'
            ]
        ]
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
        return $this->sendAsyncRequestWithProxy(new Request('GET', $url))->then(function (ResponseInterface $response) {
            $html = new Crawler($response->getBody()->getContents());

            $raplaceTags = self::OVERRIDE_REPLACE_TAGS;

            $selector = "//div[@id='block-purple-content']";

            $textNode = $html->filterXPath($selector);

            $this->selectorsRemover->remove(
                "
            //div[contains(@class, 'more_style_one')]|
            //div[contains(@class, 'text-article')]//article//blockquote[contains(@class, 'twitter-tweet')]//text()|
            //div[contains(@class, 'text-article')]//article//blockquote[contains(@class, 'instagram-media')]//text()|
            //img[contains(@src, 'default_images')]|
            //img[contains(@data-src, 'default_images')]|
            //div[contains(@class, 'more_style_three')]|
            //div[@class='top_big_img_article__info__inside__title']|
            ",
                $textNode
            );

            $newsLinks = $textNode->filterXPath('//a');
            $this->newsCutter->cutterByLinks($newsLinks);

            $text = $textNode->filterXPath(
                "
            //h1|
            //h2|
            //div[contains(@class, 'big_photo__img')]//img|
            //div[contains(@class, 'big_photo__img')]//iframe|
            //div[contains(@class, 'text-article')]//article//p|
            //div[contains(@class, 'text-article')]//article//img|
            //div[contains(@class, 'text-article')]//article//iframe|
            //div[contains(@class, 'text-article')]//article//blockquote//a|
            //div[contains(@class, 'text-article')]//article//blockquote|
            //div[@class='big_photo__description']|
            //p
            "
            );

            $this->baseUrls->addVideoUrl('iz.ru/');
            $imageNodes = $textNode->filterXPath(
                "
            //div[contains(@class, 'big_photo__img')]//img|
            //div[contains(@class, 'text-article')]//article//img|
            "
            );
            $isNeedPrviewImg = !$imageNodes->count();

            $result = $this->XPathParser->parse($text, $raplaceTags, $this->baseUrls, $isNeedPrviewImg);

            return $result;
        });
    }


    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));

            $html = new Crawler($response->getBody()->getContents());

            $selector = "//div[@id='block-purple-content']";
            $articlesNode = $html->filterXPath($selector);

            $articles = $articlesNode->filterXPath("//div[contains(concat(' ', normalize-space(@class), ' '), ' node__cart__item ')]");

            $lastAddedPublicationTime = $this->lastPublicationTime;
            $basePath = 'https://iz.ru';
            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    $linkNode = $node->filterXPath("//a[@class='node__cart__item__inside']");
                    $pageLink = $basePath . $linkNode->attr('href');

                    $articlePubDate = $node->filterXPath("//time");
                    $pubDateAttr = $articlePubDate->attr('datetime');

                    $this->selectorsRemover->remove('//time', $node);

                    $title = $node->filterXPath("//a[@class='node__cart__item__inside']")->text();

                    if (!$pubDateAttr) {
                        continue;
                    }
                    $publicationDate = $this->createDateFromString($pubDateAttr, false);

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
}
