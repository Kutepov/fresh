<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\ru\Rg;

use common\components\scrapers\common\ArticleBodyScraper;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\BaseUrls;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\XPathParserV2;
use common\components\scrapers\common\symfony\SiteParserModule\Infrastructure\SelectorsRemover;
use common\components\scrapers\common\services\HashImageService;
use common\components\scrapers\common\Config;
use common\components\scrapers\dto\ArticleBodyNode;
use common\components\scrapers\dto\ArticleItem;
use GuzzleHttp\Promise\Coroutine;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use Kevinrob\GuzzleCache\CacheMiddleware;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DomCrawler\Crawler;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\NewsCutter;

/**
 * Class Scraper
 * @package common\components\scrapers\sources\ru\Rg
 *
 * @Config (
 * timezone="Europe/Moscow", urls={
 * "https://rg.ru/tema/gos/",
 * "https://rg.ru/tema/ekonomika/",
 * "https://rg.ru/rf/",
 * "https://rg.ru/tema/mir/",
 * "https://rg.ru/tema/bezopasnost/",
 * "https://rg.ru/tema/obshestvo/",
 * "https://rg.ru/tema/sport/",
 * "https://rg.ru/tema/kultura/"
 * })
 */
class Scraper extends \common\components\scrapers\common\Scraper implements ArticleBodyScraper
{
    private const REPLACE_TAGS = [
        'div' => [
            [
                'contains' => 'flo',
                'attribute' => 'data-url',
                'valueType' => 'webview',
                'elementName' => 'chart',
            ],
        ],
        'rg-video' => [
            [
                'attribute' => 'file',
                'valueType' => 'video',
                'elementName' => 'video-source',
            ],
        ],
        'iframe' => [
            [
                'contains' => 'vgtrk',
                'attribute' => 'src',
                'valueType' => 'video',
                'elementName' => 'video',
            ],
        ],
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
     * @var SelectorsRemover
     */
    private $selectorsRemover;

    public function __construct(
        HashImageService $hashImageService,
        NewsCutter $newsCutter,
        SelectorsRemover $selectorsRemover,
        XPathParserV2 $XPathParser,
        $config = []
    )
    {
        $this->hashImageService = $hashImageService;
        $this->selectorsRemover = $selectorsRemover;
        $this->newsCutter = $newsCutter;
        $this->XPathParser = $XPathParser;

        parent::__construct($config);
    }

    public function parseArticleBody(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response =  yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));

            $replaceTags = self::REPLACE_TAGS;

            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//article[contains(@id, 'articleContainer')]|//head|//article[@class='b-material-wrapper__article']";

            $textNode = $html->filterXPath($selector);

            $this->selectorsRemover->remove(
                "
            //div[contains(@class, 'b-read-more')]|
            //blockquote[contains(@class, 'twitter-tweet')]//text()|
            //blockquote[contains(@class, 'instagram-media')]//text()|
            //div[contains(@data-src, 'visualisation')]|
            ",
                $textNode
            );

            $newsLinks = $textNode->filterXPath("//div[contains(@class, 'b-material-wrapper__text')]//a");
            $this->newsCutter->cutterByLinks($newsLinks);

            $text = $textNode->filterXPath(
                "
            //div[contains(@class, 'b-material-img')]//picture//img[contains(@class, 'b-material-img__img')]|
            //div[contains(@class, 'b-material-wrapper__text')]//p|
            //div[contains(@class, 'b-material-wrapper__text')]//div[contains(@class, 'incut')]|
            //div[contains(@class, 'b-material-wrapper__text')]//blockquote[contains(@class, 'twitter-tweet')]/a|
            //div[contains(@class, 'b-material-wrapper__text')]//blockquote[contains(@class, 'instagram-media')]|
            //div[contains(@class, 'b-material-wrapper__text')]//div[contains(@class, 'flourish-embed')]|
            //div[contains(@class, 'b-material-wrapper__text')]//ul|
            //div[contains(@class, 'b-material-wrapper__text')]//ol|
            //div[contains(@class, 'b-material-wrapper__text')]//iframe|
            //div[contains(@class, 'b-material-wrapper__text')]//rg-video|
            //div[@class='b-material-wrapper__lead']|
            //div[@class='Section']|
            "
            );
            $imageNodes = $textNode->filterXPath(
                "
            //div[contains(@class, 'b-material-img')]//picture//img[contains(@class, 'b-material-img__img')]|
            "
            );
            $isNeedPrviewImg = !$imageNodes->count();

            $result = $this->XPathParser->parse($text, $replaceTags, null, $isNeedPrviewImg);

            $gallery = $textNode->filterXPath('//rg-photoreport');

            if (0 !== $gallery->count()) {
                $carousel = yield $this->parseGallery($gallery);
                if ($carousel) {
                    $result->add($carousel);
                }
            }

            yield $result;
        });
    }


    private function parseGallery(Crawler $galleryNode)
    {
        return Coroutine::of(function () use ($galleryNode) {
            try {
                $dataUrl = $galleryNode->attr('data-url');
                $requestUrl = "https://photoreports-api.rg.ru/api/get?url={$dataUrl}&comp";

                $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $requestUrl));
                $responseJson = json_decode($response->getBody()->getContents(), true);


                $photos = $responseJson['photos'];
                $carouselImages = [];

                foreach ($photos as $key => $value) {
                    $link = $value['filepath'];
                    if (!$link) {
                        continue;
                    }
                    $scheme = parse_url($link, PHP_URL_SCHEME);
                    $isStartsWithDoubleSlash = (bool)('//' === substr($link, 0, 2));

                    if (!$scheme) {
                        if ($isStartsWithDoubleSlash) {
                            $link = 'https:' . $link;
                        } else {
                            $link = 'https://rg.ru' . $link;
                        }
                    }

                    $carouselImages[] = $this->hashImageService->hashImage($link);
                }

                $result = new ArticleBodyNode('carousel', $carouselImages);
            } catch (\Throwable $exception) {
                return null;
            }

            yield $result;
        });
    }


    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));

            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents());

            $selector = "//div[@class='b-news__body']//div[@class='b-news__list']";
            $articlesNode = $html->filterXPath($selector);

            $articles = $articlesNode->filterXPath("//div[@class='b-news__list-item ']");

            $lastAddedPublicationTime = $this->lastPublicationTime;
            $basePath = 'https://rg.ru';
            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    $linkNode = $node->filterXPath('//h2//a');
                    $pageLink = $basePath . $linkNode->attr('href');
                    $title = $linkNode->text();

                    /** @var ResponseInterface $pageContent */
                    $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                    if ($this->isNeedSkipArticle($pageContent)) {
                        continue;
                    }

                    $html = new Crawler($pageContent->getBody()->getContents());

                    $articlePubDate = $html->filterXPath("//head//meta[@property='article:published_time']")->first();
                    $pubDateAttr = $articlePubDate->attr('content');

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
