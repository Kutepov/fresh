<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\ua\Rbc\uk;

use common\components\guzzle\Guzzle;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\XPathParserV2;
use common\components\scrapers\common\services\HashImageService;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\NewsCutter;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\SelectorsRemover;
use common\components\scrapers\common\ArticleBodyScraper;
use common\components\scrapers\dto\ArticleBody;
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
 * @package common\components\scrapers\sources\ua\Rbc\uk
 *
 * @Config (timezone="Europe/Kiev", urls={
 * "https://coronavirus.rbc.ua",
 * "https://www.rbc.ua/ukr/politics",
 * "https://www.rbc.ua/ukr/accidents",
 * "https://www.rbc.ua/ukr/society",
 * "https://www.rbc.ua/ukr/economic",
 * "https://www.rbc.ua/ukr/finance",
 * "https://www.rbc.ua/ukr/hitech",
 * "https://www.rbc.ua/ukr/energetics",
 * "https://www.rbc.ua/ukr/transport",
 * "https://www.rbc.ua/ukr/sport",
 * "https://www.rbc.ua/ukr/company"
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
                'valueType' => 'text',
                'elementName' => 'paragraph',
            ],
        ],
        'img' => [
            [
                'valueType' => 'proxyJpg',
                'elementName' => 'image',
                'attribute' => 'data-src',
                'contains' => '/',
            ],
            [
                'valueType' => 'proxyJpg',
                'elementName' => 'image',
            ],
        ],
        'button' => [
            [
                'valueType' => 'carousel',
                'elementName' => 'image',
                'attribute' => 'data-mfp-src',
                'contains' => 'static',
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

    public function __construct(
        SelectorsRemover $selectorsRemover,
        HashImageService $hashImageService,
        NewsCutter $newsCutter,
        XPathParserV2 $XPathParser,
        $config = []
    )
    {
        $this->selectorsRemover = $selectorsRemover;
        $this->hashImageService = $hashImageService;
        $this->XPathParser = $XPathParser;
        $this->newsCutter = $newsCutter;

        parent::__construct($config);
    }

    public function parseArticleBody(string $url): PromiseInterface
    {
        return $this->sendAsyncRequestWithProxy(new Request('GET', $url))->then(function (ResponseInterface $response) {

            $pageContent = $response->getBody()->getContents();

            $html = new Crawler();
            $html->addHtmlContent($pageContent, 'UTF-8');


            $selector = "//article";

            $textNode = $html->filterXPath($selector)->first();

            $this->selectorsRemover->remove(
                "//*[contains(@id, 'read_more')]|
            //div[contains(@class, 'publication-social')]|
            //ul[not(node())]|
            //a[contains(text(), 'Читайте') and contains(text(), 'нас') and contains(text(), 'Google')]",
                $textNode
            );

            $newsLinks = $textNode->filterXPath("//div[contains(@class, 'publication-sticky-container')]//a");
            $this->newsCutter->cutterByLinks($newsLinks);

            $text = $textNode->filterXPath(
                "//img|
//p[not(ancestor::blockquote)]|
//iframe[contains(@src, 'youtube.com')]|
//iframe[contains(@src, 'instagram.com')]|
//img|
//blockquote[contains(@class, 'instagram-media')]|
//blockquote[not(contains(@class, 'twitter-tweet'))]|
//ul|
//ol|
//blockquote[contains(@class, 'twitter-tweet')]//a[contains(@href, 'status')]|
//iframe[contains(@data-src, 'youtube.com')]|
//button[@class='content-slider__item__link']"
            );

            $result = new ArticleBodyWithDescription();
            $images = [];
            $replaceTags = self::REPLACE_TAGS;
            $text->each(
                function (Crawler $node) use ($replaceTags, &$result, &$images) {
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
                        $attribute = $entry['attribute'] ?? null;
                        if (isset($attrValue) && 'src' === $attrValue) {
                            $attrValue = trim($attrValue, " \t\n\r\0\x0B\/");
                            $scheme = parse_url($attrValue, PHP_URL_SCHEME);
                            if (!$scheme) {
                                $attrValue = 'https://'.$attrValue;
                            }

                            $elementValue = $attrValue;
                        }

                        if ('data-instgrm-permalink' === $valueType) {
                            if ((bool) $node->attr($attribute)) {
                                $instagramSrc = $this->instagramHelper->generateEmbedUrl($node->attr($attribute));
                                $result->add(new ArticleBodyNode($elementName, $instagramSrc));
                                break;
                            }
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

                        if ('carousel' === $valueType) {
                            $image = $node->attr($entry['attribute'] ?? '');
                            if (!$image) {
                                continue;
                            }
                            $images[] = $this->hashImageService->hashImage('https://www.rbc.ua'.$image);
                        }

                        if ('text' === $valueType) {
                            $text = trim($node->text(), " \t\n\r\0\x0B");
                            $text = preg_replace('/\s\s+/', ' ', $text);
                            if (mb_strlen($text) < 2) {
                                continue;
                            }
                            $elementValue = $text;
                        }

                        if ('src' === $valueType) {
                            $elementValue = $node->attr('src');
                        }

                        if ('video' === $valueType) {
                            $src = $node->attr('src') ?: $node->attr($attribute);
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

                        if ('proxyJpg' === $valueType) {
                            $url = $attrValue ?? $node->attr('src');
                            if (!$url) {
                                continue;
                            }

                            if (false === strpos($url, 'http')) {
                                $url = 'https://www.rbc.ua'.$url;
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

            if (count($images)) {
                $result->add(new ArticleBodyNode('carousel', $images));
            }

            $description = $this->XPathParser->parseDescription(
                $html,
                '//div[@class="publication-lead"]//p[1]',
                true,
                $this->XPathParser::DESCRIPTION_TAG_HTML
            )->getNodes()[0]->getValue();
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

            $selector = "//div[@class='nano-content']";
            $articlesNode = $html->filterXPath($selector)->first();

            $articles = $articlesNode->filterXPath("//div[@class='item']");

            $lastAddedPublicationTime = $this->lastPublicationTime;

            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                $this->selectorsRemover->remove('//span[@class="time"]', $node);
                try {
                    $linkNode = $node->filterXPath('//a')->first();
                    $pageLink = $linkNode->attr('href');
                    $title = $linkNode->text();

                    $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                    if ($this->isNeedSkipArticle($pageContent)) {
                        continue;
                    }
                    $html = new Crawler();
                    $html->addHtmlContent($pageContent->getBody()->getContents(), 'UTF-8');

                    $articleData = json_decode($html->filterXPath("//script[@type = 'application/ld+json']")->first()->text());

                    $articlePubDate = $articleData->datePublished;

                    if (!$articlePubDate) {
                        continue;
                    }
                    $publicationDate = $this->createDateFromString($articlePubDate);

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

    protected function proxyEnablingAttempt(): ?int
    {
        return Guzzle::PROXY_ALWAYS_ENABLED;
    }
}