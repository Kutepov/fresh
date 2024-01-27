<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\by\Tvr;

use common\components\scrapers\common\ArticleBodyScraper;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\NewsCutter;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\BaseUrls;
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
 * @package common\components\scrapers\sources\by\Tvr
 *
 * @Config (
 * timezone="Europe/Minsk", urls={
 * "https://www.tvr.by/news/chp/",
 * "https://www.tvr.by/news/ekonomika/",
 * "https://www.tvr.by/news/glavnyy-efir/",
 * "https://www.tvr.by/news/kultura/",
 * "https://www.tvr.by/news/novosti_regiona/",
 * "https://www.tvr.by/news/obshchestvo/",
 * "https://www.tvr.by/news/politika/",
 * "https://www.tvr.by/news/prezident/",
 * "https://www.tvr.by/news/regiony/",
 * "https://www.tvr.by/news/sport/",
 * "https://www.tvr.by/news/v_mire/",
 * "https://www.tvr.by/news/zona_x/"
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
                'elementName' => 'video',
                'valueType' => 'video-source',
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

    /**
     * @var BaseUrls
     */
    private $BaseUrls;

    public function __construct(
        HashImageService $hashImageService,
        SelectorsRemover $selectorsRemover,
        XPathParserV2 $XPathParserV2,
        NewsCutter $newsCutter,
        BaseUrls $BaseUrls,
        $config = []
    )
    {
        $this->hashImageService = $hashImageService;
        $this->selectorsRemover = $selectorsRemover;
        $this->XPathParser = $XPathParserV2;
        $this->newsCutter = $newsCutter;
        $this->BaseUrls = $BaseUrls;

        parent::__construct($config);
    }


    private function parseVideoSource(Crawler $node)
    {
        $scriptText = $node->text();
        preg_match('/file:"(.*.mp4)"/', $scriptText, $output_array);
        $source = $output_array[1];
        if ($source) {
            if (!filter_var($source, FILTER_VALIDATE_URL)) {
                $source = 'https://www.tvr.by' . $source;
            }
            return $source;
        }

        return false;
    }

    public function parseArticleBody(string $url): PromiseInterface
    {
        return $this->sendAsyncRequestWithProxy(new Request('GET', $url))->then(function (ResponseInterface $response) {

            $replaceTags = self::OVERRIDE_REPLACE_TAGS;

            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//div[contains(@class, 'detail news')]";

            $textNode = $html->filterXPath($selector);

            $this->selectorsRemover->remove(
                "
            //div[@class = 'metabox']|
            //blockquote[contains(@class, 'instagram-media')]//text()|
            //blockquote[contains(@class, 'twitter-tweet')]//text()|
            //div[contains(@class, 'fb-post')]//text()|
            ",
                $textNode
            );

            $newsLinks = $textNode->filterXPath('//div[@class = "content"]//a');
            $this->newsCutter->cutterByLinks($newsLinks);

            $text = $textNode->filterXPath(
                "
            //div[contains(@class, 'item-img')]//img|
            //div[@class = 'content']//img|
            //div[@class = 'content']/text()[not(ancestor::p)]|
            //div[@class = 'content']/a//text()[not(ancestor::p)]|
            //div[@class = 'content']//p[not(ancestor::li) and not(ancestor::blockquote)]|
            //div[@class = 'content']//h3|
            //div[@class = 'content']//h2|
            //div[@class = 'content']//h1|
            //div[@class = 'content']//ul|
            //div[@class = 'content']//ol|
            //div[@class = 'content']//iframe|
            //div[@class = 'content']//div|
            //div[@class = 'content']//blockquote[contains(@class, 'instagram-media')]|
            //div[@class = 'content']//blockquote[contains(@class, 'twitter-tweet')]//a|
            //div[@class = 'content']//div[contains(@class, 'fb-post')]|
            //script[contains(text(), '/upload/video')]
            "
            );

            $imageNodes = $textNode->filterXPath('//img');

            $this->BaseUrls->addImageUrl('https://www.tvr.by');
            $BaseUrls = $this->BaseUrls;

            $result = new ArticleBody();
            $text->each(
                function (Crawler $node) use ($replaceTags, &$result, &$imageCount, &$images, $BaseUrls) {
                    $nodeName = $node->nodeName();
                    if (!array_key_exists($nodeName, $replaceTags)) {
                        return;
                    }

                    $elem = $replaceTags[$nodeName];
                    foreach ($elem as $entry) {
                        if (isset($entry['attribute'], $entry['contains'])) {
                            $attrValue = $node->attr($entry['attribute']);
                            if (!$attrValue) {
                                continue;
                            }

                            $contains = $entry['contains'];
                            if (false === strpos($attrValue, $contains)) {
                                continue;
                            }
                        }

                        $elementName = $entry['elementName'];
                        $valueType = $entry['valueType'];
                        $attribute = $entry['attribute'] ?? '';
                        $contains = $entry['contains'] ?? '';

                        if (isset($attrValue) && 'src' === $attrValue) {
                            $attrValue = trim($attrValue, " \t\n\r\0\x0B\/");
                            $scheme = parse_url($attrValue, PHP_URL_SCHEME);
                            if (!$scheme) {
                                $attrValue = 'https://' . $attrValue;
                            }

                            $elementValue = $attrValue;
                        }

                        if ('youtube-video-id' === $valueType) {
                            $videoId = $node->attr($entry['attribute'] ?? '');
                            if (!$videoId) {
                                continue;
                            }

                            $elementValue = $this->youtubeHelper->generateUrlForId($videoId);
                        }

                        if ('instagram-id' === $valueType) {
                            $postId = $node->attr($entry['attribute'] ?? '');
                            if (!$postId) {
                                continue;
                            }
                            $elementValue = $this->instagramHelper->generateUrlForId($postId);
                        }

                        if ('twitter-id' === $valueType) {
                            $postId = $node->attr($entry['attribute'] ?? '');
                            if (!$postId) {
                                continue;
                            }
                            $elementValue = $this->twitterHelper->generateUrlForId($postId);
                        }

                        if ('telegram' === $valueType) {
                            $isSingle = '' === $node->attr('data-single') ? 1 : 0;
                            $dataTelegramPost = $node->attr($attribute);
                            if ((bool)$dataTelegramPost) {
                                $telegramSrc = "https://t.me/{$dataTelegramPost}?embed=1&single={$isSingle}";
                                $elementValue = $telegramSrc;
                            }
                        }

                        if ('text' === $valueType) {
                            $text = preg_replace("/\s+/u", ' ', $node->text());
                            $text = trim($text);

                            if (mb_strlen($text) < 2) {
                                continue;
                            }
                            $elementValue = $text;
                        }

                        if (in_array($valueType, ['src', 'href', 'data-instgrm-permalink', 'data-src'])) {
                            $attrValue = $node->attr($valueType) ?? '';
                            if ('data-instgrm-permalink' === $valueType || ('href' === $valueType && strpos($attrValue, 'instagram.com'))) {
                                $elementValue = $this->instagramHelper->generateEmbedUrl($attrValue);
                            }
                            else {
                                $elementValue = $attrValue;
                            }
                        }

                        if ('webview' === $valueType) {
                            $url = $node->attr($attribute);
                            if (!$url) {
                                continue;
                            }
                            $elementValue = $url;
                        }

                        if ('video' === $valueType) {
                            $src = $node->attr('src') ?? $node->attr($attribute);

                            if (!$src) {
                                continue;
                            }


                            $value = trim($src, " \t\n\r\0\x0B\/");
                            $urlSegments = parse_url($value);
                            $scheme = parse_url($value, PHP_URL_SCHEME);
                            if (!$scheme) {
                                $value = 'https://' . $value;
                            }
                            $generateUrl = '';

                            stream_context_set_default([
                                'ssl' => [
                                    'verify_peer' => false,
                                    'verify_peer_name' => false,
                                ],
                            ]);

                            if (!empty($videoUrls) && !$scheme) {
                                foreach ($videoUrls as $key => $urlValue) {
                                    if (!array_key_exists('scheme', $urlSegments)) {
                                        $generateUrl .= 'https://';
                                    }

                                    if (!array_key_exists('host', $urlSegments)) {
                                        $generateUrl .= $urlValue;
                                    }
                                    else {
                                        if (null != $urlSegments['host']) {
                                            $generateUrl .= $urlSegments['host'];
                                        }
                                    }

                                    if (array_key_exists('path', $urlSegments)) {
                                        if (null != $urlSegments['path']) {
                                            $generateUrl .= $urlSegments['path'];
                                        }
                                    }

                                    if (array_key_exists('query', $urlSegments)) {
                                        if (null != $urlSegments['query']) {
                                            $generateUrl .= $urlSegments['query'];
                                        }
                                    }

                                    if (array_key_exists('fragment', $urlSegments)) {
                                        if (null != $urlSegments['fragment']) {
                                            $generateUrl .= '#' . $urlSegments['fragment'];
                                        }
                                    }

                                    if (@get_headers($generateUrl, 1)) {
                                        $elementValue = $generateUrl;
                                        break;
                                    }
                                    else {
                                        $generateUrl = '';
                                    }
                                }
                            }
                            else {
                                if (@get_headers($value, 1)) {
                                    $elementValue = $value;
                                }
                                elseif (@get_headers('https://' . $value, 1)) {
                                    $elementValue = 'https://' . $value;
                                }
                            }
                        }

                        if ('table' === $valueType) {
                            $tableElement = $this->tableService->getTableElement($node);
                        }

                        if ('proxyJpg' === $valueType) {
                            $url = $node->attr($attribute);
                            if (!$url) {
                                continue;
                            }

                            $isBase64 = strpos($url, 'base64');

                            if (false !== $isBase64) {
                                continue;
                            }

                            if ('srcset' === $attribute) {
                                $pics = explode(',', $url);
                                $toUse = explode(' ', $pics[0]);
                                $url = $toUse[0]; //to get the useful part of the item
                            }

                            $isStartsWithSlash = (bool)('/' === substr($url, 0, 1));


                            $scheme = parse_url($url, PHP_URL_SCHEME);
                            $isStartsWithDoubleSlash = (bool)('//' === substr($url, 0, 2));


                            if (!$scheme) {
                                if ($isStartsWithDoubleSlash) {
                                    $url = 'https:' . $url;
                                    $scheme = parse_url($url, PHP_URL_SCHEME);
                                }
                            }

                            if (null !== $BaseUrls) {
                                $imageUrls = $BaseUrls->getImageUrls();
                                if (count($imageUrls) && !$scheme) {
                                    if (!$isStartsWithSlash) {
                                        $url = '/' . $url;
                                    }
                                    $url = $imageUrls[0] . $url;
                                }
                            }


                            $link = $this->hashImageService->hashImage($url);
                            $elementValue = $link;
                        }


                        if ('video-source' === $valueType) {
                            $videoSource = $this->parseVideoSource($node);
                            $result->add(new ArticleBodyNode('video-source', $videoSource));
                        }

                        if ('ul' === $valueType || 'ol' === $valueType) {
                            $liElements = [];
                            $node->children()->each(
                                function (Crawler $node) use (&$liElements) {
                                    $liElements[] = trim($node->text(), " \t\n\r\0\x0B\/");
                                }
                            );

                            $elementValue = $liElements;
                        }

                        if (isset($elementValue)) {
                            $result->add(new ArticleBodyNode($elementName, $elementValue));
                            break;
                        }
                    }
                }
            );


            return $result;
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//div[contains(@class, 'row')]//div[contains(@class, 'item-views')]";
            $articlesNode = $html->filterXPath($selector);

            $articles = $articlesNode->filterXPath("//div[contains(@class, 'inner-news-item')]");

            $basePath = 'https://www.tvr.by';

            $lastAddedPublicationTime = $this->lastPublicationTime;
            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    $linkNode = $node->filterXPath("//div[@class='title']/a");
                    $pageLink = $basePath . $linkNode->attr('href');
                    $title = $linkNode->text();

                    $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                    if ($this->isNeedSkipArticle($pageContent)) {
                        continue;
                    }
                    $html = new Crawler();
                    $html->addHtmlContent($pageContent->getBody()->getContents(), 'UTF-8');

                    $articlePubDate = $html->filterXPath("//meta[@itemprop='datePublished']")->first();
                    $pubDateAttr = $articlePubDate->attr('content');
                    if (!$pubDateAttr) {
                        continue;
                    }

                    $publicationDate = $this->createDateFromString($node->filterXPath("//div[@class='period pull-left']")->first()->text());

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
