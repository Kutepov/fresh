<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\ru\NewsRu;

use common\components\scrapers\common\ArticleBodyScraper;
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
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\NewsCutter;

/**
 * Class Scraper
 * @package common\components\scrapers\sources\ru\NewsRu
 *
 * @Config (
 * timezone="Europe/Moscow", urls={
 * "https://www.newsru.com/allnews/",
 * "https://www.newsru.com/auto/",
 * "https://www.newsru.com/blog/",
 * "https://www.newsru.com/cinema/",
 * "https://www.newsru.com/finance/",
 * "https://www.newsru.com/hitech/",
 * "https://www.newsru.com/realty/",
 * "https://www.newsru.com/russia/",
 * "https://www.newsru.com/sport/",
 * "https://www.newsru.com/world/"
 * })
 */
class Scraper extends \common\components\scrapers\common\Scraper implements ArticleBodyScraper
{

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
        return $this->sendAsyncRequestWithProxy(new Request('GET', $url))->then(function (ResponseInterface $response) {
            $html = new Crawler($response->getBody()->getContents());

            $selector = "//div[@class='content-main']//div[@class='article']//div[contains(@class, 'article-text')]";

            $textNode = $html->filterXPath($selector);

            $this->selectorsRemover->remove(
                "
            //blockquote[contains(@class, 'instagram-media')]//text()|
            //blockquote[contains(@class, 'twitter-tweet')]//text()|
            ",
                $textNode
            );

            $newsLinks = $textNode->filterXPath('//a');
            $this->newsCutter->cutterByLinks($newsLinks);

            $text = $textNode->filterXPath(
                "
            //p|
            //ul|
            //ol|
            //iframe|
            //blockquote[contains(@class, 'instagram-media')]|
            //blockquote[contains(@class, 'twitter-tweet')]//a|
            //script|
            //div[@class='article-img-place']//img
            "
            );

            $result = $this->XPathParser->parse($text);
            return $result;
        });
    }


    private function parseGallery(Crawler $imagesNodes): ?ArticleBodyNode
    {
        try {
            $images = [];
            $self = $this;
            $imagesNodes->each(
                function (Crawler $node) use (&$images, $self) {
                    $styleAttr = $node->attr('style');
                    if ($styleAttr) {
                        preg_match("/background-image\:\s(url\()([a-zA-z\\'\:\/0-9\.]*)/", $styleAttr, $matches);
                        $images[] = $self->hashImageService->hashImage($matches[2]);
                    }
                    else {
                        return;
                    }
                }
            );

            return new ArticleBodyNode('carousel', $images);
        } catch (\Throwable $exception) {
            return null;
        }
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));

            $html = new Crawler($response->getBody()->getContents());

            $selector = "//div[@class='body-page-center-column']";
            $articlesNode = $html->filterXPath($selector);
            $articles = $articlesNode->filterXPath("//div[@class='inner-news-item']");

            $lastAddedPublicationTime = $this->lastPublicationTime;
            $basePath = 'https://www.newsru.com';
            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    $linkNode = $node->filterXPath("//a[@class='index-news-title']");
                    $pageLink = $linkNode->attr('href');
                    if (!preg_match('#^https?://#i', $pageLink)) {
                        $pageLink = $basePath . $pageLink;
                    }

                    if (!preg_match('#^https?://www\.newsru\.com/#', $pageLink)) {
                        continue;
                    }

                    $title = $linkNode->text();

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
