<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\ua\Gazeta\uk;

use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\ExternalServiceHelper\InstagramHelper;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\XPathParserV2;
use common\components\scrapers\common\services\HashImageService;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\NewsCutter;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\SelectorsRemover;
use common\components\scrapers\common\ArticleBodyScraper;
use common\components\scrapers\dto\ArticleBodyNode;
use common\components\scrapers\dto\ArticleBodyWithDescription;
use common\components\scrapers\dto\ArticleItem;
use GuzzleHttp\Promise\Coroutine;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DomCrawler\Crawler;
use common\components\scrapers\common\Config;

/**
 * Class Scraper
 * @package common\components\scrapers\sources\ua\Gazeta\uk
 *
 * @Config (timezone="Europe/Kiev", urls={
"https://api.gazeta.ua/api/section/stream&?category=avto",
"https://api.gazeta.ua/api/section/stream&?category=house",
"https://api.gazeta.ua/api/section/stream&?category=donbas",
"https://api.gazeta.ua/api/section/stream&?category=health",
"https://api.gazeta.ua/api/section/stream&?category=history",
"https://api.gazeta.ua/api/section/stream?category=krym",
"https://api.gazeta.ua/api/section/stream?category=culture",
"https://api.gazeta.ua/api/section/stream?category=economics",
"https://api.gazeta.ua/api/section/stream?category=politics",
"https://api.gazeta.ua/api/section/stream?category=celebrities",
"https://api.gazeta.ua/api/section/stream?category=edu-and-science",
"https://api.gazeta.ua/api/section/stream?category=life",
"https://api.gazeta.ua/api/section/stream?category=sport"
 * })
 */
class Scraper extends \common\components\scrapers\common\Scraper implements ArticleBodyScraper
{

    private const REPLACE_TAGS = [
        'a' => [
            [
                'contains' => 'status',
                'attribute' => 'href',
                'valueType' => 'href',
                'elementName' => 'twitter',
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
                'valueType' => 'carousel',
                'elementName' => 'carousel',
                'attribute' => 'class',
                'contains' => 'photo-collage',
            ],
            [
                'valueType' => 'text',
                'elementName' => 'paragraph',
            ],
        ],
        'img' => [
            [
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
                'contains' => 'instagram.com',
                'attribute' => 'src',
                'valueType' => 'video',
                'elementName' => 'video',
            ],
            [
                'contains' => 'obozrevatel.com',
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
        'meta' => [
            [
                'attribute' => 'content',
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
     * @var HashImageService
     */
    private $hashImageService;

    /**
     * @var XPathParserV2
     */
    private $XPathParser;

    /**
     * @var InstagramHelper
     */
    private $instagramHelper;

    /**
     * @var NewsCutter
     */
    private $newsCutter;

    public function __construct(
        SelectorsRemover $selectorsRemover,
        HashImageService $hashImageService,
        NewsCutter $newsCutter,
        XPathParserV2 $XPathParser,
        InstagramHelper $instagramHelper,
        $config = []
    )
    {
        $this->selectorsRemover = $selectorsRemover;
        $this->hashImageService = $hashImageService;
        $this->XPathParser = $XPathParser;
        $this->newsCutter = $newsCutter;
        $this->instagramHelper = $instagramHelper;

        parent::__construct($config);
    }

    public function parseArticleBody(string $url): PromiseInterface
    {
        return $this->sendAsyncRequestWithProxy(new Request('GET', $url))->then(function (ResponseInterface $response) {

            $pageContent = $response->getBody()->getContents();

            $html = new Crawler();
            $html->addHtmlContent($pageContent, 'UTF-8');

            $selector = "//head|//section[contains(@class, 'article-content')]";

            $textNode = $html->filterXPath($selector);

            $this->selectorsRemover->remove(
                "
            //strong[contains(text(), 'ЧИТАЙТЕ ТАКОЖ')]|//div[contains(@class, 'back-block')]
            //blockqoute[contains(@class, 'instagram-media')]//text()|
            ",
                $textNode
            );

            $newsLinks = $textNode->filterXPath('//a');
            $this->newsCutter->cutterByLinks($newsLinks);

            $filterXpathString = '//article/p[not(blockquote[contains(@class, "twitter-tweet")])]|
            //article//div[contains(@class, "interview-question")]/p|
            //article//div[contains(@class, "photo-collage")]|
            //article//iframe[contains(@src, "youtube.com")]|
            //article//iframe[contains(@src, "instagram.com")]|
            //article//iframe|
            //img[not(ancestor::div[@class="photo-collage"]) and not(contains(@class, "modal-content js-modal-image")) ]|
            //article//blockquote|
            //blockqoute[contains(@class, "instagram-media")]|
            //iframe[contains(@scr, "t.me")]|
            ';

            $text = $textNode->filterXPath(
                $filterXpathString
            );

            if ($text->first()->nodeName() !== 'img') {
                $filterXpathString .= '//meta[@property="og:image"]|';
                $text = $textNode->filterXPath($filterXpathString);
            }

            $result = new ArticleBodyWithDescription();
            $replaceTags = self::REPLACE_TAGS;
            $text->each(
                function (Crawler $node) use ($replaceTags, &$result) {
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

                            $result->add(new ArticleBodyNode($elementName, $attrValue));
                        }

                        if ('data-instgrm-permalink' === $valueType) {
                            if ((bool) $node->attr($attribute)) {
                                $instagramSrc = $this->instagramHelper->generateEmbedUrl($node->attr($attribute));
                                $result->add(new ArticleBodyNode($elementName, $instagramSrc));
                                break;
                            }
                        }

                        if ('carousel' === $valueType) {
                            $images = [];
                            $imageNodes = $node->filterXPath('//img');
                            /** @var \DOMElement $image */
                            foreach ($imageNodes->getIterator() as $image) {
                                $url = $image->getAttribute('src');
                                $url = preg_replace('/a_.+\./', 'w_1000.', $url);
                                $images[] = $this->hashImageService->hashImage($url);
                            }

                            $result->add(new ArticleBodyNode($elementName, $images));
                        }

                        if ('youtube-video-id' === $valueType) {
                            $videoId = $node->attr($entry['attribute'] ?? '');
                            if (!$videoId) {
                                continue;
                            }
                            $result->add(
                                new ArticleBodyNode(
                                    $elementName, $this->youtubeHelper->generateUrlForId($videoId)
                                )
                            );
                        }

                        if ('instagram-id' === $valueType) {
                            $postId = $node->attr($entry['attribute'] ?? '');
                            if (!$postId) {
                                continue;
                            }
                            $result->add(
                                new ArticleBodyNode(
                                    $elementName, $this->instagramHelper->generateUrlForId($postId)
                                )
                            );
                        }

                        if ('text' === $valueType) {
                            $text = trim($node->text(), " \t\n\r\0\x0B");
                            $text = preg_replace('/\s\s+/', ' ', $text);
                            if (mb_strlen($text) < 2) {
                                continue;
                            }
                            $result->add(new ArticleBodyNode($elementName, $text));
                        }

                        if ('src' === $valueType) {
                            $result->add(new ArticleBodyNode($elementName, $node->attr('src')));
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
                            $result->add(new ArticleBodyNode($elementName, $value));
                        }

                        if ('proxyJpg' === $valueType) {
                            $url = $attribute ? $node->attr($attribute) : $node->attr('src');
                            if (!$url) {
                                continue;
                            }

                            $link = $this->hashImageService->hashImage($url);
                            $result->add(new ArticleBodyNode($elementName, $link));
                        }

                        if ('ul' === $valueType || 'ol' === $valueType) {
                            $liElements = [];
                            $node->children()->each(
                                function (Crawler $node) use (&$liElements) {
                                    $liElements[] = trim($node->text(), " \t\n\r\0\x0B\/");
                                }
                            );
                            $result->add(new ArticleBodyNode($elementName, $liElements));
                        }
                    }
                }
            );

            $description = $this->XPathParser->parseDescription($html, '//article//p[1]')->getNodes()[0]->getValue();
            $result->setDescription($description);

            return $result;
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addHtmlContent($this->getHtml($pageContent->getBody()->getContents()), 'UTF-8');

            $selector = "//div[contains(@class, 'news-wrapper')]";
            $articlesNode = $html->filterXPath($selector)->first();

            $articles = $articlesNode->filterXPath("//div[@class='clearfix']");

            $lastAddedPublicationTime = $this->lastPublicationTime;

            $baseUrl = 'https://gazeta.ua';

            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    $linkNode = $node->filterXPath('//a[contains(@class, "news-title")]')->first();
                    $this->selectorsRemover->remove("//section[contains(@class, 'w-marker-')]", $linkNode);
                    $pageLink = $baseUrl.$linkNode->attr('href');
                    $title = $linkNode->text();

                    $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                    if ($this->isNeedSkipArticle($pageContent)) {
                        continue;
                    }
                    $html = new Crawler();
                    $html->addHtmlContent($pageContent->getBody()->getContents(), 'UTF-8');
                    $articlePubDate = $html->filterXPath("//head//meta[@property='article:published_time']")->first();
                    $pubDateAttr = @$articlePubDate->attr('content');
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

    private function getHtml(string $data): string
    {
        $start = stripos($data, 'html":"');
        $length = stripos($data, '","success"') - $start;
        $result = substr($data, $start+7, $length-7);
        return json_decode('["'.$result.'"]')[0];
    }
}