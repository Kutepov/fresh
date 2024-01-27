<?php

declare(strict_types=1);

namespace common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper;

use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\ExternalServiceHelper\RamblerHelper;
use common\components\scrapers\dto\ArticleBodyNode;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\ExternalServiceHelper\InstagramHelper;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\ExternalServiceHelper\YoutubeHelper;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\HashImageService;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\TableService;
use common\components\scrapers\dto\ArticleBody;
use Symfony\Component\DomCrawler\Crawler;

class XPathParser
{
    private const DEFAULT_REPLACE_TAGS = [
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
                'elementName' => 'text',
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
                'attribute' => 'data-id',
                'valueType' => 'rambler',
                'elementName' => 'video',
            ]
        ],
        'img' => [
            [
                'valueType' => 'proxyJpg',
                'elementName' => 'image',
                'attribute' => 'data-src',
                'contains' => '/',
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
                'contains' => 'www.facebook.com',
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
     * @var YoutubeHelper
     */
    private $youtubeHelper;

    /**
     * @var RamblerHelper
     */
    private $ramblerHelper;

    /**
     * @var TableService
     */
    private $tableService;

    public function __construct(
        HashImageService $hashImageService,
        InstagramHelper $instagramHelper,
        YoutubeHelper $youtubeHelper,
        TableService $tableService,
        RamblerHelper $ramblerHelper
    ) {
        $this->hashImageService = $hashImageService;
        $this->instagramHelper = $instagramHelper;
        $this->youtubeHelper = $youtubeHelper;
        $this->tableService = $tableService;
        $this->ramblerHelper = $ramblerHelper;
    }

    public function parse(
        Crawler $crawler,
        ?array $overrideTags = null,
        string $imageBaseUrl = null,
        bool $isNeedPrviewImg = false
    ): ArticleBody {
        $result = new ArticleBody();
        $replaceTags = $overrideTags ?? self::DEFAULT_REPLACE_TAGS;
        $crawler->each(
            function (Crawler $node) use ($replaceTags, &$result, $imageBaseUrl, $isNeedPrviewImg) {
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

                    if ('rambler' === $valueType) {
                        $videoId = $node->attr($entry['attribute'] ?? '');
                        if (!$videoId) {
                            continue;
                        }

                        $elementValue = $this->ramblerHelper->generateUrlForId($videoId);
                    }

                    if ('instagram-id' === $valueType) {
                        $postId = $node->attr($entry['attribute'] ?? '');
                        if (!$postId) {
                            continue;
                        }
                        $elementValue = $this->instagramHelper->generateUrlForId($postId);
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
                        } else {
                            $elementValue = $attrValue;
                        }
                    }

                    if ('video' === $valueType) {
                        $src = $node->attr('src');
                        if (!$src) {
                            continue;
                        }

                        $value = trim($src, " \t\n\r\0\x0B\/");
                        $scheme = parse_url($value, PHP_URL_SCHEME);
                        if (!$scheme) {
                            $value = 'https://'.$value;
                        }

                        $elementValue = $value;
                    }

                    if ('table' === $valueType) {
                        $tableElement = $this->tableService->getTableElement($node);
                    }

                    if ('proxyJpg' === $valueType) {
                        $url = $node->attr($attribute);
                        if (!$url) {
                            continue;
                        }

                        if ('link' === $nodeName || 'meta' === $nodeName) {
                            if (!$isNeedPrviewImg) {
                                continue;
                            }
                        }

                        if ($imageBaseUrl && false === strpos($url, 'http')) {
                            $url = $imageBaseUrl.$url;
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
                    }
                    break;
                }
            }
        );

        return $result;
    }
}
