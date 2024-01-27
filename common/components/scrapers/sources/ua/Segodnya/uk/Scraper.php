<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\ua\Segodnya\uk;

use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\XPathParserV2;
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
 * @package common\components\scrapers\sources\ua\Segodnya\uk
 *
 * @Config (timezone="Europe/Kiev", urls={
 *    "https://economics.segodnya.ua/ua",
 *    "https://lifestyle.segodnya.ua/ua",
 *    "https://politics.segodnya.ua/ua",
 *    "https://sport.segodnya.ua/ua",
 *    "https://ukraine.segodnya.ua/ua",
 *    "https://world.segodnya.ua/ua"
 * })
 */
class Scraper extends \common\components\scrapers\common\Scraper implements ArticleBodyScraper
{
    private const REPLACE_TAGS = [
        'a' => [
            [
                'contains' => 't.co',
                'attribute' => 'href',
                'valueType' => 'href',
                'elementName' => 'twitter',
            ],
            [
                'contains' => 'instagram.com',
                'attribute' => 'src',
                'valueType' => 'instagram-id',
                'elementName' => 'instagram',
            ],
            [
                'containsRegexp' => '*twitter.com/*/status*',
                'attribute' => 'href',
                'valueType' => 'href',
                'elementName' => 'twitter',
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
        'b' => [
            [
                'valueType' => 'text',
                'elementName' => 'paragraph',
            ],
        ],
        'h1' => [
            [
                'valueType' => 'text',
                'elementName' => 'paragraph',
            ],
        ],
        'h2' => [
            [
                'valueType' => 'text',
                'elementName' => 'caption',
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
                'valueType' => 'carousel',
                'elementName' => 'carousel',
            ],
            [
                'valueType' => 'twitter',
                'elementName' => 'twitter',
                'attribute' => 'data-oembed',
                'contains' => 'twitter.com',
            ],
            [
                'valueType' => 'football',
                'elementName' => 'football-table',
                'attribute' => 'class',
                'contains' => 'football',
            ],
            [
                'valueType' => 'instagram',
                'elementName' => 'instagram',
                'attribute' => 'data-oembed',
                'contains' => 'instagram.com',
            ],
        ],
        'img' => [
            [
                'attribute' => 'src',
                'valueType' => 'proxyJpg',
                'elementName' => 'image',
            ],
            [
                'attribute' => 'srcset',
                'valueType' => 'proxyJpg',
                'elementName' => 'image',
            ],
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
            [
                'contains' => 'youtube.com',
                'attribute' => 'data-src',
                'valueType' => 'video',
                'elementName' => 'video',
            ],
            [
                'contains' => 'twitter.com',
                'attribute' => 'src',
                'valueType' => 'video',
                'elementName' => 'video',
            ],
            [
                'contains' => 'twitter.com',
                'attribute' => 'data-src',
                'valueType' => 'video',
                'elementName' => 'video',
            ],
            [
                'contains' => 'www.facebook.com',
                'attribute' => 'src',
                'valueType' => 'video',
                'elementName' => 'facebook',
            ],
            [
                'contains' => 'facebook.com',
                'attribute' => 'data-src',
                'valueType' => 'video',
                'elementName' => 'video',
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
                'contains' => 'text/javascript',
                'attribute' => 'type',
                'elementName' => 'video-source',
                'valueType' => 'video',
            ],
            [
                'elementName' => 'carousel',
                'valueType' => 'carousel',
            ],
            [
                'attribute' => 'data-telegram-post',
                'elementName' => 'telegram',
                'valueType' => 'telegram',
            ],
        ],
        'link' => [
            [
                'attribute' => 'href',
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
     * @var XPathParserV2
     */
    private $xpathParser;


    /**
     * @var NewsCutter
     */
    private $newsCutter;

    public function __construct(
        SelectorsRemover $selectorsRemover,
        NewsCutter $newsCutter,
        XPathParserV2 $xpathParser,
        $config = []
    )
    {
        $this->selectorsRemover = $selectorsRemover;
        $this->newsCutter = $newsCutter;
        $this->xpathParser = $xpathParser;

        parent::__construct($config);
    }

    public function parseArticleBody(string $url): PromiseInterface
    {
        return $this->sendAsyncRequestWithProxy(new Request('GET', $url))->then(function (ResponseInterface $response) use ($url) {
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            try {
                $url = $html->filterXPath("//head//meta[@property='og:url']")->first()->attr('content');
            } catch (\InvalidArgumentException $e) {
                $url = $html->filterXPath("//head//meta[@name='twitter:url']")->first()->attr('content');
            }

            $isLifeStyleLink = strpos((string)$url, '/lifestyle');

            $selector = $isLifeStyleLink ? "//div[contains(@class,'article-content')]" : "//article[contains(@class,'article')]|";

            $textNode = $html->filterXPath($selector);

            if (!$isLifeStyleLink) {
                //TODO: /p/strong Читайте також: https://sport.segodnya.ua/ua/sport/euro_2020/zavodnoy-apelsin-nazval-sostav-protiv-ukrainy-na-evro-2020-1527736.html
                $this->selectorsRemover->remove(
                    "//div[contains(@class, 'article__header_social')]|
                //div[@class='article__reade-also']//img|
                //div[contains(@class, 'article__header_description')]|
                //p[contains(text(),'Реклама')]|
                //p[contains(text(),'Все подробности в спецтеме')]|
                //p/span[contains(@class, 'article__reade-also')]|
                //style|
                //p[not(text())][not(node())]|
                //p[not(*)][not(normalize-space())]|
                //p[contains(text(), 'function') or contains(text(), 'var ')]|
                //div[contains(@class, 'article__body')]/p/strong[contains(text(), 'Смотрите на нашем сайте')]|
                //p[contains(text(), 'Інші новини на тему:')]|
                //div[contains(@class, 'article__body')]/p/strong[contains(text(), 'Читайте также')]|
                //div[contains(@class, 'article__body')]/p/strong[contains(text(), 'Читайте також')]|
                //div[contains(@class, 'article__body')]/p/strong[contains(text(), 'ЧИТАЙТЕ ТАКЖЕ')]|
                //div[contains(@class, 'article__content')]/p/strong[contains(text(), 'Смотрите на нашем сайте')]|
                //div[contains(@class, 'article__content')]/p/strong[contains(text(), 'Читайте также')]|
                //div[contains(@class, 'article__content')]/h2[contains(text(), 'Читайте также')]|
                //div[contains(@class, 'article__content')]/p/strong[contains(text(), 'Читайте також')]|
                //div[contains(@class, 'article__content')]/p/strong[contains(text(), 'ЧИТАЙТЕ ТАКЖЕ')]|
                //div[contains(@class, 'article__body')]/p[contains(text(), 'Читайте также')]|
                //div[contains(@class, 'article__body')]/p[contains(text(), 'Читайте також')]|
                //div[contains(@class, 'article__body')]/p[contains(text(), 'ЧИТАЙТЕ ТАКЖЕ')]|
                //div[contains(@class, 'article__content')]/p[contains(text(), 'Смотрите на нашем сайте')]|
                //div[contains(@class, 'article__content')]/p[contains(text(), 'Читайте также')]|
                //div[contains(@class, 'article__content')]/p[contains(text(), 'Читайте також')]|
                //div[contains(@class, 'article__content')]/p[contains(text(), 'Читайте також')]//following-sibling::ul|
                //div[contains(@class, 'article__content')]/p[contains(text(), 'ЧИТАЙТЕ ТАКЖЕ')]|
                //div[contains(@class, 'article__content')]/p[contains(text(), 'Узнайте другие новости:')]|
                //div[contains(@class, 'banner-wrap')]|
                //div[contains(@id, 'segtest')]|
                //figcaption|
                //img[contains(@src, 'icons-siri.png')]|
                //div[contains(@class, 'article__content')]//article[contains(@class, 'b-article b-article_imgSqM')]|
                //img[@class='article__smile']|
                //h2[contains(text(), 'Читайте також')]|
                //h2[contains(text(), 'Читайте також')]/following-sibling::ul|
                //h2//strong[contains(text(), 'Раніше ми писали')]|
                //h2//strong[contains(text(), 'Раніше ми писали')]//following-sibling::ul|
                //h2[@class='b-article__title']|
                //div[@class='b-article__img']|
                ",
                    $textNode
                );

                $text = $textNode->filterXPath(
                    "//script[contains(@type, 'text/javascript')][contains(text(), 'contentId:')]|
                //script[contains(@src, 'telegram')]|
                //div[contains(@class, 'article__body')]/p[not(blockquote[contains(@class, 'twitter-tweet')])]|
                //div[contains(@class, 'article__body')]/blockquote|
                //div[contains(@class, 'article__body')]/ul|
                //div[contains(@class, 'article__body')]//h2|
                //div[contains(@class, 'article__content')]/p[not(blockquote[contains(@class, 'twitter-tweet')])]|
                //div[contains(@class, 'article__content')]/blockquote|
                //div[contains(@class, 'article__content')]/ul|
                //div[contains(@class, 'article__content')]/ol|
                //div[contains(@class, 'article__content')]//h2|
                //div[contains(@class, 'article__content')]//img|
                //div[@class='article__altimg']//b|
                //div[contains(@class, 'article__subtitle')]|
                //script[contains(text(), 'gallery:')]|
                //div[contains(@class, 'article__body')]//img|
                //iframe|
                //div[contains(@class, 'oembed-provider-twitter')]|
                //div[contains(@class, 'embeddedContent')][contains(@class, 'oembed-provider-instagram')]|
                //div[@class='football']|
                //a[contains(@href, 'status')]|
                //iframe[contains(@data-src, 'facebook.com/plugins/video.php')]|
                "
                );
            } else {
                $this->selectorsRemover->remove(
                    "
                //div[contains(@class, 'article-banner')]|
                //a[contains(@class, 'read-our-telegram')]|
                //div[contains(@class, 'article-content__author')]|
                //p[not(*)][not(normalize-space())]|
                //figcaption|
                //*[contains(text(), 'Всі подробиці в спецтемі')]|
                //*[contains(text(), 'Все подробности в спецтеме')]|
                //*[contains(text(), ' Раніше ми рекомендували')]|
                //*[contains(text(), 'Ранее мы рекомендовали')]|
                //*[contains(text(), 'Ранее мы выяснили')]|
                //*[contains(text(), 'Раніше ми з')]|
                //*[contains(text(), 'Нагадаємо')]|
                //*[contains(text(), 'Напомним')]|
                //*contains(text(), 'Узнайте другие новости:')]|
                ",
                    $textNode
                );

                $text = $textNode->filterXPath(
                    "
                //p|
                //h2|
                //ol|
                //ul|
                //iframe|
                //div[contains(@class, 'embeddedContent')][contains(@class, 'oembed-provider-twitter')]|
                //div[contains(@class, 'embeddedContent')][contains(@class, 'oembed-provider-instagram')]|
                //figure[contains(@class, 'article-img')]//img|
                //figure//img|
                //div[@class='figure']//img|
                //div[@class='article-img']//img|
                //div[@class='content-gallery']//div[contains(@class, 'owl-carousel')]|
                //blockquote[@class='instagram-media']|
                "
                );
            }

            $newsLinks = $textNode->filterXPath('//a');
            $this->newsCutter->cutterByLinks($newsLinks);

            $customProcessing = function (Crawler $node, array $replaceTags) use ($url) {
                $nodeName = $node->nodeName();

                $elem = $replaceTags[$nodeName];
                foreach ($elem as $entry) {
                    if (isset($entry['attribute'], $entry['contains'])) {
                        $attrValue = $node->attr($entry['attribute']);
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
                            $scriptText = $node->text();
                            preg_match("/contentId\:\s([a-zA-z\\'\/0-9\.]*)/", $scriptText, $output_array);
                            if (isset($output_array[1])) {
                                $host = parse_url($url, PHP_URL_HOST);
                                $videoLink = str_replace(["'", '"'], '', $output_array[1]);
                                $absoluteVideoLink = 'https://' . $host . $videoLink;
                                return (new ArticleBodyNode($elementName, $absoluteVideoLink));
                            } else {
                                continue;
                            }
                        }
                        if ('carousel' === $valueType) {
                            $scriptText = $node->text();
                            preg_match_all("/img\:\s([a-zA-z\\'\/0-9\.:]*)/", $scriptText, $output_array);
                            $images = [];
                            if ((bool)count($output_array[1])) {
                                foreach ($output_array[1] as $src) {
                                    $validUrl = str_replace(["'", '"'], '', $src);
                                    $images[] = $this->hashImageService->hashImage($validUrl);
                                }
                                return count($images) ? (new ArticleBodyNode($elementName, $images)) : false;
                            } else {
                                continue;
                            }
                        }
                    }

                    if ('carousel' === $valueType) {
                        $images = [];
                        $imageNodes = $node->filterXPath("//div[@class='content-gallery-item']");
                        if ($imageNodes->count()) {
                            $imageNodes->each(
                                function (Crawler $contentNode) use (&$images) {
                                    $img = $contentNode->filterXPath("//div[contains(@class, 'content-gallery-item-image')]");
                                    $img = $img->attr('style');
                                    preg_match("/url\\('([a-zA-z\:\.\/0-9]*)/", $img, $output_array);
                                    $images[] = $this->hashImageService->hashImage($output_array[1]);
                                }
                            );
                            return count($images) ? (new ArticleBodyNode($elementName, $images)) : false;
                        }
                    }

                }
                return false;
            };
            $result = $this->xpathParser->parse($text, self::REPLACE_TAGS, null, false, $customProcessing, false, true);

            $description = $this->xpathParser->parseDescription($html, '//div[@class="article__content"]//p[1]')->getNodes()[0]->getValue();
            $result->setDescription($description);

            return $result;
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addHtmlContent($pageContent->getBody()->getContents(), 'UTF-8');

            $selector = "//article[contains(@class, 'special-topic')]|//div[@class='main main_home']|//div[@class='main']";
            $articlesNode = $html->filterXPath($selector)->first();

            $xpath = "//article[contains(@class, 'b-article')]|//div[contains(@class, 'st__news-list')]//ul//li";
            $articles = $articlesNode->filterXPath($xpath);
            $lastAddedPublicationTime = $this->lastPublicationTime;

            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {

                    $linkNode = $node->filterXPath('//h4//a|//div[@class="b-article__title"]//a|//a')->first();
                    $pageLink = $linkNode->attr('href');
                    $title = $node->filterXPath('//h3|//*[@class="b-article__title"]')->text();

                    $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                    if ($this->isNeedSkipArticle($pageContent)) {
                        continue;
                    }
                    $html = new Crawler();
                    $html->addHtmlContent($pageContent->getBody()->getContents(), 'UTF-8');

                    $dataArticle = $html->filterXPath(
                        "//script[@type='application/ld+json']")
                        ->first();

                    if (!$dataArticle->count()) {
                        continue;
                    }

                    $dataArticle = json_decode($dataArticle->text(), true);

                    if (!isset($dataArticle['datePublished'])) {
                        continue;
                    }
                    $publicationDate = $this->createDateFromString($dataArticle['datePublished']);

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