<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\by\Minsknews;

use common\components\scrapers\common\ArticleBodyScraper;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\NewsCutter;
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

/**
 * Class Scraper
 * @package common\components\scrapers\sources\by\Minsknews
 *
 * @Config (
 * timezone="Europe/Minsk", urls={
 * "https://minsknews.by/category/cityhold/",
 * "https://minsknews.by/category/culture/",
 * "https://minsknews.by/category/economics/",
 * "https://minsknews.by/category/insidents/",
 * "https://minsknews.by/category/novosti-mira/",
 * "https://minsknews.by/category/palitra/",
 * "https://minsknews.by/category/society/",
 * "https://minsknews.by/category/sport/"
 * })
 */
class Scraper extends \common\components\scrapers\common\Scraper implements ArticleBodyScraper
{
    private const OVERRIDE_REPLACE_TAGS = [];

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


    private function parseGallery(Crawler $carouselNode): ?ArticleBodyNode
    {
        try {
            $imagesNodes = $carouselNode->filterXPath('//div[contains(@class, "rsContent")]//a[contains(@class, "rsImg")]');
            $images = [];
            if ($imagesNodes->count()) {
                $self = $this;
                $imagesNodes->each(
                    function (Crawler $node) use (&$images, $self) {
                        $imgSrc = $node->attr('href');
                        if ($imgSrc) {
                            $scheme = parse_url($imgSrc, PHP_URL_SCHEME);
                            if (!$scheme) {
                                $imgSrc = 'https:'.$imgSrc;
                            }
                            $images[] = $self->hashImageService->hashImage($imgSrc);
                        } else {
                            return;
                        }
                    }
                );

                return new ArticleBodyNode('carousel', $images);
            } else {
                return null;
            }
        } catch (\Throwable $exception) {
            return null;
        }
    }


    public function parseArticleBody(string $url): PromiseInterface
    {
        return $this->sendAsyncRequestWithProxy(new Request('GET', $url))->then(function (ResponseInterface $response) {

            $raplaceTags = self::OVERRIDE_REPLACE_TAGS;

            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//div[@class = 'td-post-content']";

            $textNode = $html->filterXPath($selector);

            $this->selectorsRemover->remove(
                "
            //div[@class = 'video-subscribe-header']|
            //*[contains(text(), 'Смотрите также:')]|
            //blockquote[contains(@class, 'instagram-media')]//text()|
            //blockquote[contains(@class, 'twitter-tweet')]//text()|
            //div[contains(@class, 'fb-post')]//text()|
            //p//*[contains(text(), 'Читайте также:')]|
            //p//*[contains(text(), 'Читайте также:')]//../../../following-sibling::p|
            //p[preceding-sibling::p//*[contains(text(), 'Читайте также:')]]|
            //div[@id = 'featured-media' and preceding-sibling::p//*[contains(text(), 'Смотрите также:')]]|
            ",
                $textNode
            );

            $newsLinks = $textNode->filterXPath('//a');
            $this->newsCutter->cutterByLinks($newsLinks);

            $text = $textNode->filterXPath(
                "
            //div/text()|
            //p[not(ancestor::table)]|
            //img[not(ancestor::noscript) and not(ancestor::div[contains(@class, 'royalSlider')])]|
            //iframe|
            //ul|
            //h2|//h3|
            //ol|
            //blockquote[contains(@class, 'instagram-media')]|
            //blockquote[contains(@class, 'twitter-tweet')]//a|
            //div[contains(@class, 'fb-post')]|
            //ul|
            //source[@type='video/mp4']|
            //div[contains(@class, 'rsNav')]|
            //table|
            "
            );

            $imageNodes = $textNode->filterXPath("//img[not(ancestor::noscript) and not(ancestor::div[contains(@class, 'royalSlider')])]|");
            $isNeedPrviewImg = !$imageNodes->count();

            $result = $this->XPathParser->parse($text, $raplaceTags, null, $isNeedPrviewImg);

            $carouselSelector = "//div[contains(@class, 'royalSlider')]";
            $carouselNode = $html->filterXPath($carouselSelector);

            if ($carouselNode->count()) {
                $carousel = $this->parseGallery($carouselNode);
                if ($carousel) {
                    $result->add($carousel);
                }
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

            $selector = "//div[@class='td-ss-main-content']";
            $articlesNode = $html->filterXPath($selector);

            $articles = $articlesNode->filterXPath("//div[@class='td-ss-main-content']/div[@class='td-block-row']/div[contains(@class, 'td-block-span')]");

            $lastAddedPublicationTime = $this->lastPublicationTime;
            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    $linkNode = $node->filterXPath("//h3[contains(@class, 'td-module-title')]//a");
                    $pageLink = $linkNode->attr('href');
                    $title = $linkNode->text();

                    $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                    if ($this->isNeedSkipArticle($pageContent)) {
                        continue;
                    }
                    $html = new Crawler();
                    $html->addHtmlContent($pageContent->getBody()->getContents(), 'UTF-8');

                    $articlePubDate = $html->filterXPath("//meta[@property='article:published_time']")->first();
                    if (!$articlePubDate->count()) {
                        continue;
                    }
                    $pubDateAttr = $articlePubDate->attr('content');

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
