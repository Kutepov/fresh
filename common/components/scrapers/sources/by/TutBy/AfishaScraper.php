<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\by\TutBy;

use Carbon\Carbon;
use common\components\scrapers\common\ArticleBodyScraper;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\ExternalServiceHelper\YoutubeHelper;
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
 * @package common\components\scrapers\sources\by\TutBy
 *
 * @Config (
 * timezone="Europe/Minsk", urls={
 * "https://afisha.tut.by/news/"
 * })
 */
class AfishaScraper extends \common\components\scrapers\common\Scraper implements ArticleBodyScraper
{
    private const REPLACE_TAGS = [
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
            [
                'elementName' => 'video',
                'valueType' => 'youtube',
            ],
        ],
    ];

    private const MONTHS = [
        'января' => '-01-',
        'февраля' => '-02-',
        'марта' => '-03-',
        'апреля' => '-04-',
        'мая' => '-05-',
        'июня' => '-06-',
        'июля' => '-07-',
        'августа' => '-08-',
        'сентября' => '-09-',
        'октября' => '-10-',
        'ноября' => '-11-',
        'декабря' => '-12-',
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

    /** @var YoutubeHelper */
    private $youTubeHelper;

    /**
     * @var NewsCutter
     */
    private $newsCutter;

    public function __construct(
        HashImageService $hashImageService,
        SelectorsRemover $selectorsRemover,
        XPathParserV2 $XPathParserV2,
        NewsCutter $newsCutter,
        YoutubeHelper $youtubeHelper,
        $config = []
    )
    {
        $this->hashImageService = $hashImageService;
        $this->selectorsRemover = $selectorsRemover;
        $this->XPathParser = $XPathParserV2;
        $this->newsCutter = $newsCutter;
        $this->youTubeHelper = $youtubeHelper;

        parent::__construct($config);
    }


    public function parseArticleBody(string $url): PromiseInterface
    {
        return $this->sendAsyncRequestWithProxy(new Request('GET', $url))->then(function (ResponseInterface $response) {

            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');


            $selector = "//div[@id = 'article_body']";

            $textNode = $html->filterXPath($selector);

                $this->selectorsRemover->remove(
                "
            //div[contains(@class, 'b-inject')]|
            //div[@class='b-addition m-simplify']
            ",
                $textNode
            );


            $newsLinks = $textNode->filterXPath('//a');
            $this->newsCutter->cutterByLinks($newsLinks);

            $text = $textNode->filterXPath(
                "
            //img|
            //p|
            //ul|
            //ol|
            //h3
            "
            );

            $replaceTags = self::REPLACE_TAGS;
            $result = new ArticleBody();

            $images = [];
            $text->each(
                function (Crawler $node) use ($replaceTags, &$result, &$imageCount, &$images) {
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
                                $attrValue = 'https://'.$attrValue;
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
                            if ((bool) $dataTelegramPost) {
                                $telegramSrc = "https://t.me/{$dataTelegramPost}?embed=1&single={$isSingle}";
                                $elementValue = $telegramSrc;
                            }
                        }

                        if ('text' === $valueType) {
                            $text = preg_replace("/\s+/u", ' ', $node->text());
                            $text = trim($text);

                            if (stripos($node->text(), 'youtu.be') !== false) {
                                $videoId = stristr($node->text(), 'youtu.be/');
                                $videoId = stristr($videoId, '"', true);
                                $videoId = substr($videoId, 9);
                                $result->add( new ArticleBodyNode('video', $this->youTubeHelper->generateUrlForId($videoId)));
                                continue;
                            }

                            if (mb_strlen($text) < 2) {
                                continue;
                            }
                            $elementValue = $text;
                        }

                        if (in_array($valueType, ['src', 'href', 'data-instgrm-permalink', 'data-src'])) {
                            $attrValue = $node->attr($valueType) ?? '';
                            if ('data-instgrm-permalink' === $valueType || ('href' === $valueType && strpos($attrValue, 'instagram.com'))) {
                                $elementValue = $this->instagramHelper->generateEmbedUrl($attrValue);
                            } else {
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
                                $value = 'https://'.$value;
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
                                    } else {
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
                                            $generateUrl .= '#'.$urlSegments['fragment'];
                                        }
                                    }

                                    if (@get_headers($generateUrl, 1)) {
                                        $elementValue = $generateUrl;
                                        break;
                                    } else {
                                        $generateUrl = '';
                                    }
                                }
                            } else {
                                if (@get_headers($value, 1)) {
                                    $elementValue = $value;
                                } elseif (@get_headers('https://'.$value, 1)) {
                                    $elementValue = 'https://'.$value;
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

                            $scheme = parse_url($url, PHP_URL_SCHEME);
                            $isStartsWithDoubleSlash = (bool) ('//' === substr($url, 0, 2));


                            if (!$scheme) {
                                if ($isStartsWithDoubleSlash) {
                                    $url = 'https:'.$url;
                                    $scheme = parse_url($url, PHP_URL_SCHEME);
                                }
                            }

                            $link = $this->hashImageService->hashImage($url);
                            $elementValue = $link;
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

            $selector = "//div[@class='list_category m-s_comment m-s_head']//ul";
            $articlesNode = $html->filterXPath($selector);

            $articles = $articlesNode->filterXPath("//li");

            $lastAddedPublicationTime = $this->lastPublicationTime;

            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    $linkNode = $node->filterXPath("//h3//a")->first();
                    $pageLink = $linkNode->attr('href');
                    $title = $linkNode->text();

                    $articlePubDate = $node->filterXPath("//p[@class='category__date']")->first()->text();
                    $publicationDate = $this->prepareTime($articlePubDate);

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


    private function prepareTime(string $rawDatetime): Carbon
    {
        [$day, $month, $year] = mb_split(' ', $rawDatetime);
        $time = new \DateTime($day . self::MONTHS[$month] . $year);
        return $this->createDateFromString($time->format('Y-m-d H:i:s'));
    }

}
