<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\ua\LB\uk;

use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\ExternalServiceHelper\InstagramHelper;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\ExternalServiceHelper\YoutubeHelper;
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
 * @package common\components\scrapers\sources\ua\LB\uk
 *
 * @Config (timezone="Europe/Kiev", urls={
 * "https://lb.ua/politics",
 * "https://lb.ua/economics",
 * "https://lb.ua/society",
 * "https://lb.ua/culture",
 * "https://lb.ua/world",
 * "https://lb.ua/sport"
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
        'h1' => [            [
                'valueType' => 'text',
                'elementName' => 'paragraph',
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
                'elementName' => 'carousel',
                'valueType' => 'carousel',
            ],
            [
                'contains' => 'read-social',
                'attribute' => 'class',
                'valueType' => 'text',
                'elementName' => 'paragraph',

            ]
        ],
        'img' => [
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
        'iframe' => [
            [
                'contains' => 'youtube.com',
                'attribute' => 'src',
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
                'contains' => 'instagram.com',
                'attribute' => 'src',
                'valueType' => 'instagram',
                'elementName' => 'instagram',
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
                'valueType' => 'twitter-tweet',
                'elementName' => 'twitter',
            ],
            [
                'valueType' => 'text',
                'elementName' => 'quote',
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
     * @var YoutubeHelper
     */
    private $youtubeHelper;

    /**
     * @var InstagramHelper
     */
    private $instagramHelper;

    public function __construct(
        SelectorsRemover $selectorsRemover,
        HashImageService $hashImageService,
        NewsCutter $newsCutter,
        XPathParserV2 $XPathParser,
        YoutubeHelper $youtubeHelper,
        InstagramHelper $instagramHelper,
        $config = []
    )
    {
        $this->selectorsRemover = $selectorsRemover;
        $this->hashImageService = $hashImageService;
        $this->XPathParser = $XPathParser;
        $this->newsCutter = $newsCutter;
        $this->youtubeHelper = $youtubeHelper;
        $this->instagramHelper = $instagramHelper;

        parent::__construct($config);
    }

    public function parseArticleBody(string $url): PromiseInterface
    {
        return $this->sendAsyncRequestWithProxy(new Request('GET', $url))->then(function (ResponseInterface $response) {

            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//article[contains(@class,'material')]";

            $textNode = $html->filterXPath($selector)->first();
            $this->selectorsRemover->remove(
                "
            //p[not(*)][not(normalize-space())]|
            //div[@class='block read-social']|
            //p//style
            ",
                $textNode
            );

            $newsLinks = $textNode->filterXPath("//div[@itemprop='articleBody']//a");
            $this->newsCutter->cutterByLinks($newsLinks);

            $text = $textNode->filterXPath(
                "
                //div[@class='image']//img|
            //div[contains(@class, 'photo-item-image')]//img|
            //div[itemprop='image']//img|
            //div[@itemprop='articleBody']/p|
            //div[@itemprop='articleBody']//blockquote//a|
            //div[@itemprop='articleBody']//blockquote[contains(@class, 'type-quote')]|
            //div[@itemprop='articleBody']//blockquote[contains(@class, 'instagram-media')]|
            //div[@itemprop='articleBody']//iframe|
            //div[@itemprop='articleBody']//h3|
            //div[@itemprop='articleBody']//ol|
            //div[@itemprop='articleBody']//ul|
            //div[contains(@class, 'slick-gallery')]|
            //div[@class='block read-social']
            "
            );

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
                        $attribute = $entry['attribute'] ?? null;

                        if ('data-instgrm-permalink' === $valueType) {
                            if ((bool) $node->attr($attribute)) {
                                $instagramAttr = $node->attr($attribute);
                                $instagramSrc = $this->instagramHelper->generateEmbedUrl($instagramAttr);
                                $result->add(new ArticleBodyNode($elementName, $instagramSrc));
                                break;
                            }
                        }

                        if ('telegram' === $valueType) {
                            if ((bool) $node->attr($attribute)) {
                                $telegramSrc = $node->attr($attribute);
                                $telegramSrc = "https://t.me/{$telegramSrc}?embed=1";
                                $result->add(new ArticleBodyNode($elementName, $telegramSrc));
                            }
                        }

                        if ('href' === $valueType) {
                            $href = $node->attr($attribute);
                            $href = stristr($href, '?', true) ? stristr($href, '?', true) : $href;
                            $result->add(new ArticleBodyNode($elementName, $href));
                            break;
                        }

                        if (isset($attrValue) && 'src' === $attrValue) {
                            $attrValue = trim($attrValue, " \t\n\r\0\x0B\/");
                            $scheme = parse_url($attrValue, PHP_URL_SCHEME);
                            if (!$scheme) {
                                $attrValue = 'https://'.$attrValue;
                            }

                            $result->add(new ArticleBodyNode($elementName, $attrValue));
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

                        if ('src' === $valueType) {
                            $result->add(new ArticleBodyNode($elementName, $node->attr($valueType)));
                        }

                        if ('text' === $valueType) {
                            //For paragraph
                            $text = trim($node->text(), " \t\n\r\0");
                            $text = preg_replace('/\s\s+/', ' ', $text);
                            if (mb_strlen($text) < 2) {
                                continue;
                            }
                            $result->add(new ArticleBodyNode($elementName, $text));
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

                            if (count($images) > 0) {
                                $result->add(new ArticleBodyNode($elementName, $images));
                            }
                        }

                        if ('video' === $valueType) {
                            $src = $node->attr($attribute);
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
                            $url = $node->attr($attribute);
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

            $description = $this->XPathParser->parseDescription($html, '//div[@class="material-h2"]//p[1]|//div[@itemprop="articleBody"]//p[1]')->getNodes()[0]->getValue();
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

            $selector = "//section[@class='col-left']//ul[@class='feed feed-main'][2]|//ul[@class='lenta']";
            $articlesNode = $html->filterXPath($selector)->first();

            $this->selectorsRemover->remove("//li[@class='adv-block']|//li[@class='split-time']|//li[@class='show-more']|//span[contains(@class, 'ico i-')]", $articlesNode);

            $articles = $articlesNode->filterXPath("//li");
            $lastAddedPublicationTime = $this->lastPublicationTime;

            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    $linkNode = $node->filterXPath('//a')->first();
                    if (!$linkNode->count()) {
                        continue;
                    }
                    $pageLink = $linkNode->attr('href');


                    if (!filter_var($pageLink, FILTER_VALIDATE_URL)) {
                        $pageLink = $this->getHost().$pageLink;
                    }

                    $articlePubDateNode = $node->filterXPath("//time")->first();
                    if (!$articlePubDateNode) {
                        continue;
                    }

                    $articlePubDate = $articlePubDateNode->attr('datetime');

                    $this->selectorsRemover->remove("//time", $node);

                    $this->selectorsRemover->remove('//nobr', $node);

                    $title = $node->filterXPath('//a[not(@class="photo-ahref")]')->text();


                    $publicationDate = $this->createDateFromString($articlePubDate, true);

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

    protected function getHost(): string
    {
        return 'https://lb.ua';
    }

}