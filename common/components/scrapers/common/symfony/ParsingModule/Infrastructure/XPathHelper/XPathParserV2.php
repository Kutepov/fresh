<?php

declare(strict_types=1);

namespace common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper;

use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\ExternalServiceHelper\RamblerHelper;
use common\components\scrapers\dto\ArticleBodyNode;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\ExternalServiceHelper\InstagramHelper;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\ExternalServiceHelper\TwitterHelper;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\ExternalServiceHelper\YoutubeHelper;
use common\components\scrapers\common\services\HashImageService;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\TableService;
use common\components\scrapers\dto\ArticleBody;
use common\components\scrapers\dto\ArticleBodyWithDescription;
use Symfony\Component\DomCrawler\Crawler;

class XPathParserV2
{
    public const DESCRIPTION_TAG_TWITTER = 'twitter:description';
    public const DESCRIPTION_TAG_OG = 'og:description';
    public const DESCRIPTION_TAG_HTML = 'description';

    private const PRIORITY_TAGS_FOR_DESCRIPTION = [
        self::DESCRIPTION_TAG_TWITTER,
        self::DESCRIPTION_TAG_OG,
        self::DESCRIPTION_TAG_HTML,
        'Description'
    ];

    private const EXCLUDE_EXTENSIONS_IMAGES = [
        '.svg'
    ];

    private const DEFAULT_REPLACE_TAGS = [
        '#text' => [
            [
                'valueType' => 'text',
                'elementName' => 'paragraph',
            ],
        ],
        'a' => [
            [
                'containsRegexp' => '*twitter.com/*/status*',
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
            [
                'attribute' => 'data-id',
                'valueType' => 'rambler',
                'elementName' => 'video',
            ],
            [
                'attribute' => 'data-e2e',
                'contains' => 'instagram-embed-https',
                'valueType' => 'instagram-id',
                'elementName' => 'instagram',
            ],
            [
                'attribute' => 'data-e2e',
                'contains' => 'youtube-embed-https',
                'elementName' => 'video',
                'valueType' => 'youtube-video-id',
            ],
            [
                'attribute' => 'data-e2e',
                'contains' => 'twitter-embed-https',
                'elementName' => 'twitter',
                'valueType' => 'twitter-id',
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
                'contains' => 'player.vimeo.com',
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
        ],
    ];

    /**
     * @var HashImageService
     */
    private $hashImageService;

    /**
     * @var InstagramHelper
     */
    private $instagramHelper;

    /**
     * @var TwitterHelper
     */
    private $twitterHelper;

    /**
     * @var YoutubeHelper
     */
    private $youtubeHelper;

    /**
     * @var TableService
     */
    private $tableService;

    /**
     * @var RamblerHelper
     */
    private $ramblerHelper;

    public function __construct(
        HashImageService $hashImageService,
        InstagramHelper $instagramHelper,
        TwitterHelper $twitterHelper,
        YoutubeHelper $youtubeHelper,
        TableService $tableService,
        RamblerHelper $ramblerHelper
    ) {
        $this->hashImageService = $hashImageService;
        $this->instagramHelper = $instagramHelper;
        $this->twitterHelper = $twitterHelper;
        $this->youtubeHelper = $youtubeHelper;
        $this->tableService = $tableService;
        $this->ramblerHelper = $ramblerHelper;
    }

    public function parse(
        Crawler $crawler,
        ?array $overrideTags = null,
        ?BaseUrls $BaseUrls = null,
        bool $isNeedPrviewImg = false,
        callable $customProcessing = null,
        bool $isRecursiveMerge = true,
        bool $classWithDescription = false
    ): ArticleBody {

        $result = $classWithDescription ? new ArticleBodyWithDescription() : new ArticleBody();
        if ($overrideTags) {
            $replaceTags = $isRecursiveMerge ? array_merge_recursive(self::DEFAULT_REPLACE_TAGS, $overrideTags) : array_merge(self::DEFAULT_REPLACE_TAGS, $overrideTags);
        }
        else {
            $replaceTags = self::DEFAULT_REPLACE_TAGS;
        }

        $crawler->each(
            function (Crawler $node) use ($replaceTags, &$result, $BaseUrls, $isNeedPrviewImg, $customProcessing) {
                if (!$this->nodeIsAvailable($node)) {
                    return;
                }

                $nodeName = $node->nodeName();
                if (!array_key_exists($nodeName, $replaceTags)) {
                    return;
                }

                if (is_callable($customProcessing)) {
                    $return = call_user_func($customProcessing, $node, $replaceTags);
                    if ($return instanceof ArticleBodyNode) {
                        $result->add($return);
                        return;
                    }
                }

                $elem = $replaceTags[$nodeName];
                foreach ($elem as $entry) {
                    if (isset($entry['attribute'], $entry['contains']) ||
                        isset($entry['attribute'], $entry['containsRegexp'])
                    ) {
                        $attrValue = $node->attr($entry['attribute']);
                        if (!$attrValue) {
                            continue;
                        }

                        $contains = $entry['containsRegexp'] ?: $entry['contains'];
                        if (isset($entry['containsRegexp']) && !fnmatch($contains, $attrValue)) {
                            continue;
                        }

                        if (isset($entry['contains']) && false === strpos($attrValue, $contains)) {
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

                        if ($entry['attribute'] === 'data-e2e') {
                            $videoId = str_replace('youtube-embed-https://www.youtube.com/watch?v=', '', $videoId);
                        }

                        if (stripos($videoId, 'https://www.youtube.com/embed/') !== false) {
                            $elementValue = $videoId;
                        } else {
                            $elementValue = $this->youtubeHelper->generateUrlForId($videoId);
                        }
                    }

                    if ('instagram-id' === $valueType) {
                        $postId = $node->attr($entry['attribute'] ?? '');
                        if (!$postId) {
                            continue;
                        }

                        if ($entry['attribute'] === 'data-e2e') {
                            $postId = str_replace('instagram-embed-https://www.instagram.com/p/', '', $postId);
                            $postId = str_replace('/', '', $postId);
                        }

                        $elementValue = $this->instagramHelper->generateUrlForId($postId);
                    }

                    if ('twitter-id' === $valueType) {
                        $postId = $node->attr($entry['attribute'] ?? '');
                        if (!$postId) {
                            continue;
                        }

                        if ($entry['attribute'] === 'data-e2e') {
                            preg_match('#\d{2,20}#', $postId, $postId);

                            if (!count($postId)) {
                                continue;
                            }

                            $postId = $postId[0];
                        } else {
                            $postId = $node->attr($entry['attribute'] ?? '');
                            if (!$postId) {
                                continue;
                            }
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

                        if (empty($text) || $nodeName !== '#text' && (mb_strlen($text) < 2 || stripos($text, 'function()') !== false || $node->attr('class') === 'twitter-tweet')) {
                            continue;
                        }
                        $elementValue = $text;
                    }

                    if (in_array($valueType, ['src', 'href', 'data-instgrm-permalink', 'data-src', 'name'])) {
                        $attrValue = $node->attr($valueType) ?? '';
                        if ('data-instgrm-permalink' === $valueType || ('href' === $valueType && strpos($attrValue, 'instagram.com'))) {
                            $elementValue = $this->instagramHelper->generateEmbedUrl($attrValue);
                        } else {
                            if ($attrValue === '') {
                                continue;
                            }
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

                        if (null !== $BaseUrls) {
                            $videoUrls = $BaseUrls->getVdeoUrls();
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
                        if (!$url || stripos($url, 'placeholder-1x1')) {
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
                        $isStartsWithSlash = (bool) ('/' === substr($url, 0, 1));

                        if ('link' === $nodeName || 'meta' === $nodeName) {
                            if (!$isNeedPrviewImg) {
                                continue;
                            }
                        }

                        if (!$scheme) {
                            if ($isStartsWithDoubleSlash) {
                                $url = 'https:'.$url;
                                $scheme = parse_url($url, PHP_URL_SCHEME);
                            }
                        }

                        if (null !== $BaseUrls) {
                            $imageUrls = $BaseUrls->getImageUrls();
                            if (count($imageUrls) && !$scheme) {
                                if (!$isStartsWithSlash) {
                                    $url = '/'.$url;
                                }
                                $url = $imageUrls[0].$url;
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

                    if ('rambler' === $valueType) {
                        $videoId = $node->attr($entry['attribute'] ?? '');
                        if (!$videoId) {
                            continue;
                        }

                        $elementValue = $this->ramblerHelper->generateUrlForId($videoId);
                    }

                    if ('carousel' === $valueType ) {
                        $liElements = [];
                        $node->children()->each(
                            function (Crawler $node) use (&$liElements, $elementName, $BaseUrls, $entry) {

                                $urlNode = $node->filterXPath('//img')->first();
                                if (!$urlNode->count()) {
                                    return;
                                }
                                $attr = $entry['img-attr'] ?? 'rel';
                                $url = $urlNode->attr($attr);

                                $scheme = parse_url($url, PHP_URL_SCHEME);
                                $isStartsWithSlash = (bool) ('/' === substr($url, 0, 1));


                                if (null !== $BaseUrls) {
                                    $imageUrls = $BaseUrls->getImageUrls();
                                    if (count($imageUrls) && !$scheme) {
                                        if (!$isStartsWithSlash) {
                                            $url = '/'.$url;
                                        }
                                        $url = $imageUrls[0].$url;
                                    }
                                }


                                $liElements[] = $this->hashImageService->hashImage($url);
                            }
                        );
                        if (count($liElements)) {
                            $result->add(new ArticleBodyNode($elementName, $liElements));
                        }
                    }



                    if (isset($elementValue)) {
                        $result->add(new ArticleBodyNode($elementName, $elementValue));
                        break;
                    }
                }
            }
        );

        return $result;
    }

    public function parseDescription(Crawler $html, string $filter = '', bool $checkMetaFirst = true, $priorityTag = null): ArticleBody
    {
        $description = null;
        $result = new ArticleBody();

        if ($checkMetaFirst) {
            $metaTags = self::PRIORITY_TAGS_FOR_DESCRIPTION;

            if (!is_null($priorityTag)) {
                array_unshift($metaTags, $priorityTag);
            }

            foreach ($metaTags as $name) {
                $meta = $html->filterXPath('//meta[@name="' . $name . '"]|//meta[@property="' . $name . '"]');
                if ($meta->count()) {
                    $description = $meta->attr('content');
                    break;
                }
            }
        }

        if (!$description && $filter) {
            $node = $html->filterXPath($filter)->first();
            $description = $node->count() ? $node->text() : null;
        }

        if ($description) {
            $result->add(new ArticleBodyNode('paragraph', trim($description)));
        }

        return $result;
    }

    private function nodeIsAvailable(Crawler $node): bool
    {
        foreach (self::DEFAULT_REPLACE_TAGS['img'] as $img) {
            foreach (self::EXCLUDE_EXTENSIONS_IMAGES as $ext) {
                if (!is_null($node->attr($img['attribute'])) && stripos($node->attr($img['attribute']), $ext) !== false) {
                    return false;
                }
            }
        }

        return true;
    }
}
