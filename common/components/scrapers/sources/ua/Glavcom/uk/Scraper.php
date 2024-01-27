<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\ua\Glavcom\uk;

use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\BaseUrls;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\CatchExeptionalParser;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\XPathParserV2;
use common\components\scrapers\common\services\HashImageService;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\NewsCutter;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\SelectorsRemover;
use common\components\scrapers\common\ArticleBodyScraper;
use common\components\scrapers\dto\ArticleBodyNode;
use common\components\scrapers\dto\ArticleItem;
use GuzzleHttp\Promise\Coroutine;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DomCrawler\Crawler;
use common\components\scrapers\common\Config;

/**
 * Class Scraper
 * @package common\components\scrapers\sources\ua\Glavcom\uk
 *
 * @Config (timezone="Europe/Kiev", urls={
"https://glavcom.ua/country.html",
"https://glavcom.ua/economics.html",
"https://glavcom.ua/kyiv.html",
"https://glavcom.ua/scotch/showbiz.html",
"https://glavcom.ua/sport.html",
"https://glavcom.ua/techno.html",
"https://glavcom.ua/techno/auto.html",
"https://glavcom.ua/topics.html",
"https://glavcom.ua/topics/gripp.html",
"https://glavcom.ua/world.html"
 * })
 */
class Scraper extends \common\components\scrapers\common\Scraper implements ArticleBodyScraper
{

    private const REPLACE_TAGS = [
        'ul' => [
            [
                'contains' => 'glide__slides',
                'attribute' => 'class',
                'valueType' => 'carousel',
                'elementName' => 'carousel',
                'img-attr' => 'data-src',
            ]
        ]
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
     * @var NewsCutter
     */
    private $newsCutter;

    /**
     * @var CatchExeptionalParser
     */
    private $catchExeptionalParser;


    public function __construct(
        SelectorsRemover $selectorsRemover,
        HashImageService $hashImageService,
        NewsCutter $newsCutter,
        XPathParserV2 $XPathParser,
        CatchExeptionalParser $catchExeptionalParser,
        $config = []
    )
    {
        $this->selectorsRemover = $selectorsRemover;
        $this->hashImageService = $hashImageService;
        $this->XPathParser = $XPathParser;
        $this->newsCutter = $newsCutter;
        $this->catchExeptionalParser = $catchExeptionalParser;

        parent::__construct($config);
    }

    public function parseArticleDescription(string $url): PromiseInterface
    {
        return $this->sendAsyncRequestWithProxy(new Request('GET', $url))->then(function (ResponseInterface $response) {
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents());
            return $this->XPathParser->parseDescription($html, '//div[@class="body"]//p[1]')->getNodes()[0]->getValue();
        });
    }

    public function parseArticleBody(string $url): PromiseInterface
    {
        return $this->sendAsyncRequestWithProxy(new Request('GET', $url))->then(function (ResponseInterface $response) {

            $pageContent = $response->getBody()->getContents();

            $html = new Crawler();
            $html->addHtmlContent($pageContent, 'UTF-8');


            $selector = '//article[contains(@class, "post")]';

            $textNode = $html->filterXPath($selector)->first();

            $this->catchExeptionalParser->catchexeption($textNode->filterXPath('//a'));

            $this->selectorsRemover->remove(
                "//ul[contains(@class, \"credits\")]|//div[contains(@class, \"vline\")]|
            //div[contains(@class, \"alt\")]|
            //div[contains(@class, 'box_media')]|
            //div[contains(@class, 'read_more')]|
            //blockquote[contains(@class, 'instagram-media')]//text()|
            //div[contains(@class, 'body')]//p[*[contains(text(), 'Читайте також:') or contains(text(), 'Читайте также:')]]|
            //div[contains(@class, 'body')]//p[*[contains(text(), 'Читайте також:') or contains(text(), 'Читайте также:')]]//following::*[1]|
            //strong[contains(text(), 'Читайте також:')]|
            //div[@class='title']|
            //div[contains(@class, 'social_buttons')]|
            //div[contains(@id, 'news-comments-block_')]|
            ",
                $textNode
            );

            $newsLinks = $textNode->filterXPath("//div[contains(@class, 'body')]//a");
            $this->newsCutter->cutterByLinks($newsLinks);

            $text = $textNode->filterXPath(
                <<<XPATH
            //div[contains(@class, "post_content")]//img[not(ancestor::li)]|
            //div[contains(@class, "post_content")]//p[not(blockquote[contains(@class, "twitter-tweet")])]|
            //div[contains(@class, "post_content")]//iframe[contains(@src, "youtube.com")]|
            //div[contains(@class, "post_content")]//iframe[contains(@src, "instagram.com")]|
            //div[contains(@class, "post_content")]//iframe[contains(@src, "facebook.com")]|
            //div[contains(@class, "post_content")]//blockquote|
            //div[contains(@class, "post_content")]//h2|
            //h1|
            //a[contains(@href, "t.me")]|
            //blockquote[contains(@class, 'twitter-tweet')]//a|
            //div[contains(@class, "post_content")]//ul|
            //script[contains(@src, "telegram")]|
            //div[@class="post_reference_content"]|
XPATH
            );

            $baseUrls = new BaseUrls();
            $baseUrls->addImageUrl('https://glavcom.ua');
            $baseUrls->addVideoUrl('https://glavcom.ua');

            $result = $this->XPathParser->parse($text, self::REPLACE_TAGS, $baseUrls, false, null, false, true);

            $carouselNode = $textNode->filterXPath("//div[contains(concat(' ', @class, ' '), ' gallery ')]");
            if ($carouselNode->count()) {
                $carousel = $this->parseGallery($carouselNode);
                if ($carousel) {
                    $result->add($carousel);
                }
            }

            $description = $this->XPathParser->parseDescription($html, '//div[@class="body"]//p[1]')->getNodes()[0]->getValue();
            $result->setDescription($description);

            return $result;
        });
    }

    private function parseGallery(Crawler $carouselNode): ?ArticleBodyNode
    {
        try {
            $imagesNodes = $carouselNode->filterXPath('//img');
            $images = [];
            if ($imagesNodes->count()) {
                $self = $this;
                $imagesNodes->each(
                    function (Crawler $node) use (&$images, $self) {
                        $imgSrc = $node->attr('data-big');
                        if ($imgSrc) {
                            $scheme = parse_url($imgSrc, PHP_URL_SCHEME);
                            if (!$scheme) {
                                $imgSrc = 'https://glavcom.ua' . $imgSrc;
                            }
                            $images[] = $self->hashImageService->hashImage($imgSrc);
                        }
                        else {
                            return;
                        }
                    }
                );

                return new ArticleBodyNode('carousel', $images);
            }
            else {
                return null;
            }
        } catch (\Throwable $exception) {
            return null;
        }
    }


    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addHtmlContent($pageContent->getBody()->getContents(), 'UTF-8');

            $selector = "//div[@class='container_main container_main_section']";
            $articlesNode = $html->filterXPath($selector)->first();

            $articles = $articlesNode->filterXPath("//div[@class='article_body']");

            $lastAddedPublicationTime = $this->lastPublicationTime;

            $baseUrl = 'https://glavcom.ua';

            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    $linkNode = $node->filterXPath('//a')->first();
                    if (!$linkNode->count()) {
                        continue;
                    }
                    $pageLink = $baseUrl . $linkNode->attr('href');
                    $title = $linkNode->text();


                    $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                    if ($this->isNeedSkipArticle($pageContent)) {
                        continue;
                    }
                    $html = new Crawler();
                    $html->addHtmlContent($pageContent->getBody()->getContents(), 'UTF-8');
                    $dataArticle = $html->filterXPath(
                        "//meta[@property='article:published_time']")
                        ->first();

                    if (!$dataArticle->count()) {
                        continue;
                    }

                    $pubDateAttr = $dataArticle->attr('content');

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