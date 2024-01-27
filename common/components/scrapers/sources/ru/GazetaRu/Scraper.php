<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\ru\GazetaRu;

use common\components\scrapers\common\ArticleBodyScraper;
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
 * @package common\components\scrapers\sources\ru\GazetaRu
 *
 * @Config (
 * timezone="Europe/Moscow", urls={
 * "https://www.gazeta.ru/army/",
 * "https://www.gazeta.ru/auto/",
 * "https://www.gazeta.ru/business/",
 * "https://www.gazeta.ru/culture/",
 * "https://www.gazeta.ru/lifestyle/",
 * "https://www.gazeta.ru/politics/",
 * "https://www.gazeta.ru/science/",
 * "https://www.gazeta.ru/tech/",
 * "https://www.gazeta.ru/sport/"
 * })
 */
class Scraper extends \common\components\scrapers\common\Scraper implements ArticleBodyScraper
{
    private const OVERRIDE_TAGS = [
        'span' => [
            [
                'valueType' => 'text',
                'elementName' => 'paragraph',
            ],
        ],
        'h1' => [
            [
                'valueType' => 'text',
                'elementName' => 'paragraph'
            ]
        ],
        'h2' => [
            [
                'valueType' => 'text',
                'elementName' => 'caption'
            ]
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
            [
                'attribute' => 'data-hq',
                'valueType' => 'proxyJpg',
                'elementName' => 'image',
            ],
        ],

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
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));

            $html = new Crawler($response->getBody()->getContents());

            $selector = "//div[contains(@class, 'b_main')]";

            $textNode = $html->filterXPath($selector);

            $this->selectorsRemover->remove(
                "
            //div[@itemprop='articleBody']//blockquote[contains(@class, 'instagram-media')]//text()|
            //div[@itemprop='articleBody']//blockquote[contains(@class, 'twitter-tweet')]//text()|
            //div[@itemprop='articleBody']//p[contains(text(), 'НОВОСТИ ПО ТЕМЕ:')]|
            //div[@itemprop='articleBody']//p[contains(text(), 'НОВОСТИ ПО ТЕМЕ:')]/following-sibling::p[1]|
            //div[@itemprop='articleBody']//p[contains(text(), 'НОВОСТИ ПО ТЕМЕ:')]/following-sibling::p[2]|
            //div[@itemprop='articleBody']//p[contains(text(), 'НОВОСТИ ПО ТЕМЕ:')]/following-sibling::p[3]|
            //div[contains(@class, 'preview-wrapper')]|
            //img[@class='item-image-front']|
            //img[contains(@src, '+ img +')]|
            //div[@id='replacement_video']
            ",
                $textNode
            );

            $newsLinks = $textNode->filterXPath("//div[@itemprop='articleBody']//a");
            $this->newsCutter->cutterByLinks($newsLinks);

            $text = $textNode->filterXPath(
                "
            //div[contains(@class, 'i_gallery')]//figure[1]//img[1]|
            //div[@itemprop='articleBody']//img|
            //div[@itemprop='articleBody']//p|
            //div[@itemprop='articleBody']//ul|
            //div[@itemprop='articleBody']//iframe|
            //div[@itemprop='articleBody']//ol|
            //div[@itemprop='articleBody']//blockquote[contains(@class, 'instagram-media')]|
            //div[@itemprop='articleBody']//blockquote[contains(@class, 'twitter-tweet')]/a|
            //span[@itemprop='description']|
            //figure[@class='item active']//img|
            //div[contains(@class, 'b_article-header')]//h1|
            //div[contains(@class, 'b_article-header')]//h2
            "
            );

            $imageNodes = $textNode->filterXPath(
                "
            //div[@itemprop='articleBody']//img|
            //div[contains(@class, 'i_gallery')]//img|
            "
            );
            $isNeedPrviewImg = !$imageNodes->count();

            $result = $this->XPathParser->parse($text, self::OVERRIDE_TAGS, null, $isNeedPrviewImg, null, false);

            yield $result;
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));

            $html = new Crawler($response->getBody()->getContents());

            $articles = $html->filterXPath("//div[contains(@class, 'w_col2')]|//div[contains(@class, 'w_col1')]|//div[contains(@class, 'm_simple')]");
            $lastAddedPublicationTime = $this->lastPublicationTime;
            $result = [];

            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    $linkNode = $node->filterXPath("//div[contains(@class, 'b_ear-title')]//a");
                    if (!$linkNode->count()) {
                        continue;
                    }
                    $pageLink = $linkNode->attr('href');
                    $title = $linkNode->text();

                    $pageLink = 'https://'.$this->getHost().$pageLink;

                    $articlePubDate = $node->filterXPath("//time")->first();
                    if (!$articlePubDate->count()) {
                        continue;
                    }
                    $pubDateAttr = $articlePubDate->attr('datetime');
                    if (!$pubDateAttr) {
                        continue;
                    }

                    $publicationDate = $this->createDateFromString($pubDateAttr);

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


    public function getHost(): string
    {
        return 'www.gazeta.ru';
    }
}
