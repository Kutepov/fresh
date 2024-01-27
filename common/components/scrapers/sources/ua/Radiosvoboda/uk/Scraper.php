<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\ua\Radiosvoboda\uk;

use common\components\scrapers\common\services\HashImageService;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\XPathParserV2;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\NewsCutter;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\SelectorsRemover;
use common\components\scrapers\dto\ArticleBodyNode;
use common\components\scrapers\dto\ArticleItem;
use GuzzleHttp\Promise\Coroutine;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DomCrawler\Crawler;
use common\components\scrapers\common\Config;
use common\components\scrapers\common\ArticleBodyScraper;

/**
 * Class Scraper
 * @package common\components\scrapers\sources\ua\Radiosvoboda\uk
 *
 * @Config (timezone="Europe/Kiev", urls={
"https://www.radiosvoboda.org/z/21477",
"https://www.radiosvoboda.org/news",
"https://www.radiosvoboda.org/p/4399.html",
"https://www.radiosvoboda.org/z/633",
"https://www.radiosvoboda.org/z/986",
"https://www.radiosvoboda.org/z/636",
"https://www.radiosvoboda.org/z/635",
"https://www.radiosvoboda.org/p/7440.html",
"https://www.radiosvoboda.org/z/631",
"https://www.radiosvoboda.org/z/632",
"https://www.radiosvoboda.org/donbassrealii",
"https://www.radiosvoboda.org/z/16697",
"https://www.radiosvoboda.org/z/22561",
"https://www.radiosvoboda.org/china",
"https://www.radiosvoboda.org/p/5390.html",
"https://www.radiosvoboda.org/photo",
"https://www.radiosvoboda.org/z/21477",
"https://www.radiosvoboda.org/skhemy",
"https://www.radiosvoboda.org/skhemy",
"https://www.radiosvoboda.org/z/17073",
"https://www.radiosvoboda.org/z/17085",
"https://www.radiosvoboda.org/z/16714/articles",
"https://www.radiosvoboda.org/z/22136/articles",
"https://www.radiosvoboda.org/z/20340/articles"
 * })
 */
class Scraper extends \common\components\scrapers\common\Scraper /*implements ArticleBodyScraper*/
{

    private const REPLACE_TAGS = [
        'script' => [
            [
                'contains' => 'youtube.com',
                'valueType' => 'video',
                'elementName' => 'video',
                'attribute' => 'elem_text'
            ],
            [
                'contains' => 'twitter-tweet',
                'valueType' => 'twitter',
                'elementName' => 'twitter',
                'attribute' => 'elem_text'
            ],
            [
                'contains' => 'https://www.facebook.com/plugins/post.php',
                'attribute' => 'elem_text',
                'valueType' => 'facebook',
                'elementName' => 'facebook',
            ],

            [
                'attribute' => 'elem_text',
                'contains' => 'https://www.google.com/maps/d/embed',
                'valueType' => 'map',
                'elementName' => 'map'
            ],
            [
                'contains' => 'link',
                'attribute' => 'elem_text',
                'valueType' => 'telegram',
                'elementName' => 'telegram',
            ],
        ],
        'div' => [
            [
                'contains' => 'hidden',
                'attribute' => 'class',
                'valueType' => 'carousel',
                'elementName' => 'carousel',
            ],
        ],
        'video' => [
            [
                'valueType' => 'video',
                'elementName' => 'video',
                'attribute' => 'data-fallbacksrc',
            ],
        ],
    ];

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

    /** @var HashImageService */
    private $hashImageService;

    public function __construct(
        SelectorsRemover $selectorsRemover,
        NewsCutter       $newsCutter,
        XPathParserV2    $XPathParser,
        HashImageService $hashImageService,
                         $config = []
    )
    {
        $this->selectorsRemover = $selectorsRemover;
        $this->XPathParser = $XPathParser;
        $this->newsCutter = $newsCutter;
        $this->hashImageService = $hashImageService;

        parent::__construct($config);
    }

    public function parseArticleBody(string $url): PromiseInterface
    {
        return $this->sendAsyncRequestWithProxy(new Request('GET', $url))->then(function (ResponseInterface $response) use ($url) {
            $html = new Crawler($response->getBody()->getContents());

            $selector = '//head|//div[@id="article-content"]//div[@class="wsw"]|//div[@class="post post--liveblog"][1]';

            $textNode = $html->filterXPath($selector);
            $this->selectorsRemover->remove(
                "//p[@lang='EN-US']|
                             //p[contains(text(), 'Управління Верховного комісара ООН із прав людини станом')]|
                            //*[contains(text(), 'Долучайтесь до')]|
                            //*[contains(text(), 'Читайте також:')]|
                            //div[@class='media-block also-read']|
                            //figure[@class='media-gallery-embed overlay-wrap js-media-expand']//img|
                            //ul[@class='share__list']|
                            //img[@src='/Content/responsive/img/player-spinner.png']|
                            //ul[@class='subitems']|
                            //*[contains(text(), 'No media source currently available')]|
                            //div[contains(@class, 'c-mmp__poster--video')]|
                            //div[contains(@class, 'media-pholder--audio')]|
                ",
                $textNode
            );


            $newsLinks = $textNode->filterXPath("//a");
            $this->newsCutter->cutterByLinks($newsLinks);

            $text = $textNode->filterXPath(
                "
                //meta[@property='og:image']|
            //img|
            //iframe[contains(@src, 'youtube.com')]|
            //ul|
            //iframe[contains(@src, 'facebook.com')]|
            //blockquote|
            //p[not(contains(text(), 'Viber !'))]|
            //picture//img|
            //amp-youtube|
            //amp-facebook|
            //a[@class='oembed']|
            //script[contains(@src, 'telegram')]|
            //script[contains(text(), 'renderExternalContent')]|
            //script[contains(text(), 'initInfographics')]|
            //h2|
            //figure//div[@class='hidden']|
            //video"
            );

            $customProcessing = function (Crawler $node, array $replaceTags) use ($url) {
                $nodeName = $node->nodeName();

                $elem = $replaceTags[$nodeName];
                foreach ($elem as $entry) {
                    if (isset($entry['attribute'], $entry['contains'])) {
                        $attrValue =  $entry['attribute'] === 'elem_text' ? $node->text() : $node->attr($entry['attribute']);
                        if (!$attrValue) {
                            continue;
                        }

                        $contains = $entry['contains'];
                        if (is_string($attrValue) && is_string($contains) && false === strpos($attrValue, (string)$contains)) {
                            continue;
                        }
                    }

                    $elementName = $entry['elementName'];
                    $valueType = $entry['valueType'];

                    if ('script' === $nodeName) {
                        if ('video' === $valueType) {
                            $link = str_replace('renderExternalContent(', '', $attrValue);
                            $link = str_replace('"', '', $link);
                            $link = str_replace('\'', '', $link);
                            $link = str_replace(')', '', $link);

                            if ($link) {
                                return (new ArticleBodyNode($elementName, $link));
                            }
                        }

                        if ('twitter' === $valueType) {

                            $json = str_replace('initInfographics( ', '', $node->text());
                            $json = str_replace(');', '', $json);
                            $url = stristr($json, 'https://twitter.com/');
                            if ($url) {
                                $url = stristr($url, '\"', true);

                                return (new ArticleBodyNode($elementName, $url));
                            }
                        }

                        if ('facebook' === $valueType) {
                            $url = stristr($node->text(), 'https://www.facebook.com/plugins/post.php');
                            if ($url) {
                                $url = stristr($url, '\\', true);

                                return (new ArticleBodyNode($elementName, $url));
                            }
                        }

                        if ('map' === $valueType) {
                            $json = str_replace('initInfographics( ', '', $node->text());
                            $json = str_replace(');', '', $json);
                            $url = stristr($json, 'https://www.google.com/maps/d/embed');
                            $url = stristr($url, "\\", true);

                            return (new ArticleBodyNode($elementName, $url));
                        }

                    }

                    if ('proxyJpg' === $valueType) {
                        $url = $node->attr($entry['attribute']);

                        if (!$url) {
                            continue;
                        }

                        preg_match('_w\d*_', $url, $matches);
                        $width = str_replace('w', '', $matches[0]);
                        if ((int) $width < 1200) {
                            $width = 1200;
                            $url = preg_replace('_w\d*_', '_w' . $width . '_', $url);
                        }

                        $link = $this->hashImageService->hashImage($url);

                        return (new ArticleBodyNode($elementName, $link));
                    }

                    if ('carousel' === $valueType ) {
                        $liElements = [];
                        $node->children()->each(
                            function (Crawler $node) use (&$liElements, $elementName, $entry) {

                                $url = $node->attr('data-lbox-gallery-item-src');

                                preg_match('_q\d*_', $url, $matches);
                                $quality = str_replace('w', '', $matches[0]);
                                if ((int) $quality < 80) {
                                    $quality = 100;
                                    $url = preg_replace('_q\d*_', 'q' . $quality, $url);
                                }

                                $liElements[] = $this->hashImageService->hashImage($url);
                            }
                        );

                        if (count($liElements)) {
                            return new ArticleBodyNode($elementName, $liElements);
                        }

                    }

                    if ('video' === $valueType) {
                        $src = $node->attr('data-fallbacksrc');
                        if (!$src) {
                            continue;
                        }

                        return new ArticleBodyNode($elementName, $src);

                    }

                }
                return false;
            };

            $result = $this->XPathParser->parse($text, self::REPLACE_TAGS, null, true, $customProcessing);
            return $result;
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addHtmlContent($pageContent->getBody()->getContents(), 'UTF-8');

            $selector = "//div[@id='content']";
            $articlesNode = $html->filterXPath($selector)->first();

            $articles = $articlesNode->filterXPath("//div[contains(@class, 'media-block__content')]");

            $this->selectorsRemover->remove(
                "//a[contains(@class, 'category category--mb')]",
                $articles
            );

            $lastAddedPublicationTime = $this->lastPublicationTime;

            $baseUrl = 'https://www.radiosvoboda.org';

            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);

                try {

                    $pageLinkNode = $node->filterXPath('//a');
                    $pageLink = $baseUrl . $pageLinkNode->attr('href');

                    $title = $pageLinkNode->text();

                    $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));

                    if ($this->isNeedSkipArticle($pageContent)) {
                        continue;
                    }

                    $html = new Crawler($pageContent->getBody()->getContents());

                    $ld = $html->filterXPath("//script[@type = 'application/ld+json']")->first();

                    if (!$ld->count()) {
                        continue;
                    }

                    $articleData = json_decode($ld->text());

                    $articlePubDate = $articleData->datePublished;

                    if (!$articlePubDate) {
                        continue;
                    }

                    $publicationDate = $this->createDateFromString($articlePubDate);

                    if ($publicationDate >= $lastAddedPublicationTime) {
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