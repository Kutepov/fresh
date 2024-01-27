<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\ua\Telegraf\ru;

use Carbon\Carbon;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\XPathParserV2;
use common\components\scrapers\dto\ArticleBodyNode;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\ExternalServiceHelper\InstagramHelper;
use common\components\scrapers\common\services\HashImageService;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\NewsCutter;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\SelectorsRemover;
use common\components\scrapers\common\ArticleBodyScraper;
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
 * @package common\components\scrapers\sources\ua\Telegraf\ru
 *
 * @Config (timezone="Europe/Kiev", urls={
 * "https://telegraf.com.ua/biznes",
 * "https://telegraf.com.ua/kultura",
 * "https://telegraf.com.ua/zhizn",
 * "https://telegraf.com.ua/ukraina/politika/",
 * "https://telegraf.com.ua/ukraina/mestnyiy/",
 * "https://telegraf.com.ua/ukraina/obshhestvo/",
 * "https://telegraf.com.ua/mir"
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
        'br' => [
            [
                'valueType' => 'br',
                'elementName' => 'br',
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
            ],
        ],
        'video' => [
            [
                'contains' => 'facebook.com',
                'attribute' => 'src',
                'valueType' => 'video',
                'elementName' => 'video',
            ],
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
                'contains' => 'streamable.com',
                'attribute' => 'src',
                'valueType' => 'video',
                'elementName' => 'video',
            ],
            [
                'contains' => 'megogo',
                'attribute' => 'src',
                'valueType' => 'video',
                'elementName' => 'video',
            ],
            [
                'contains' => '24tv.ua',
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
        ],
        'amp-img' => [
            [
                'valueType' => 'proxyJpg',
                'elementName' => 'image',
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
        'script' => [
            [
                'attribute' => 'data-telegram-post',
                'elementName' => 'telegram',
                'valueType' => 'telegram',
            ],
        ]
    ];

    private const MONTH = [
        'Январь' => '01',
        'Февраль' => '02',
        'Март' => '03',
        'Апрель' => '04',
        'Май' => '05',
        'Июнь' => '06',
        'Июль' => '07',
        'Август' => '08',
        'Сентябрь' => '09',
        'Октябрь' => '10',
        'Ноябрь' => '11',
        'Декабрь' => '12',
    ];

    /**
     * @var SelectorsRemover
     */
    protected $selectorsRemover;

    /**
     * @var HashImageService
     */
    protected $hashImageService;

    /**
     * @var InstagramHelper
     */
    protected $instagramHelper;

    /**
     * @var NewsCutter
     */
    protected $newsCutter;

    /**
     * @var XPathParserV2
     */
    protected $xpathParser;

    public function __construct(
        SelectorsRemover $selectorsRemover,
        HashImageService $hashImageService,
        NewsCutter $newsCutter,
        InstagramHelper $instagramHelper,
        XPathParserV2 $xpathParser,
        $config = []
    )
    {
        $this->selectorsRemover = $selectorsRemover;
        $this->hashImageService = $hashImageService;
        $this->instagramHelper = $instagramHelper;
        $this->newsCutter = $newsCutter;
        $this->xpathParser = $xpathParser;

        parent::__construct($config);
    }

    public function parseArticleBody(string $url): PromiseInterface
    {
        return $this->sendAsyncRequestWithProxy(new Request('GET', $url))->then(function (ResponseInterface $response) {
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//div[contains(@class, 'content-single__block')]|
        //div[contains(@class, 'wrapper-content__video_block')]";

            $textNode = $html->filterXPath($selector)->first();

            $this->selectorsRemover->remove(
                "//li[contains(@class, 'format-list-photo-ads')]|
            //div[contains(@class, 'gallery-num')]|
            //p[contains(text(), 'Подписывайтесь') and contains(text(), 'Telegram')]",
                $textNode
            );

            $newsLinks = $textNode->filterXPath("//div[contains(@class, 'content-single')]//a");
            $this->newsCutter->cutterByLinks($newsLinks);

            $text = $textNode->filterXPath(
                "//div[contains(@class, 'content-single__block__img')]//img|
//div[contains(@class, 'content-single__block_text_body')]/p|
//div[contains(@class, 'content-single__block_text_body')]//img|//iframe[contains(@src, 'youtube.com')]|
//div[contains(@class, 'content-single__block_text_body')]//blockquote[not(contains(@class, 'twitter-tweet'))]|
//div[contains(@class, 'content-single__block_text_body')]/ul[not(@class = 'format-list-photo')]|
//iframe[contains(@src, '24tv.ua')]|
//iframe[contains(@src, 'facebook.com')]|
//iframe[contains(@src, 'streamable')]|
//iframe|
//ul[@class = \"format-list-photo\"]//p|
//ul[@class = \"format-list-photo\"]//img|
//script[@data-telegram-post]|
//blockquote[contains(@class, 'twitter-tweet')]//a[contains(@href, 'status')]"
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
                        if (!isset($entry['attribute'], $entry['contains'])) {
                            $valueType = $entry['valueType'];
                            $elementName = $entry['elementName'];
                            break;
                        }

                        $attrValue = $node->attr($entry['attribute']) ?? '';
                        $contains = $entry['contains'];
                        $attribute = $entry['attribute'] ?? '';
                        if (false !== strpos($attrValue, $contains)) {
                            $elementName = $entry['elementName'];
                            if ('instagram' === $elementName) {
                                if ($attrValue) {
                                    $instagramSrc = $this->instagramHelper->generateEmbedUrl($attrValue);
                                    $result->add(new ArticleBodyNode($elementName, $instagramSrc));
                                    break;
                                }
                            }
                            if ('src' === $entry['attribute']) {
                                $attrValue = trim($attrValue, " \t\n\r\0\x0B\/");
                                $scheme = parse_url($attrValue, PHP_URL_SCHEME);
                                if (!$scheme) {
                                    $attrValue = 'https://' . $attrValue;
                                }
                            }

                            $result->add(new ArticleBodyNode($elementName, $attrValue));

                            return;
                        }
                    }

                    if (!isset($valueType, $elementName)) {
                        return;
                    }

                    if ('telegram' === $valueType) {
                        $isSingle = '' === $node->attr('data-single') ? 1 : 0;
                        $dataTelegramPost = $node->attr('data-telegram-post');
                        if ((bool)$dataTelegramPost) {
                            $telegramSrc = "https://t.me/{$dataTelegramPost}?embed=1&single={$isSingle}";
                            $elementValue = $telegramSrc;
                            $result->add(new ArticleBodyNode($elementName, $elementValue));
                        }
                    }


                    if ('text' === $valueType) {
                        $text = trim($node->text(), " \t\n\r\0\x0B");
                        $text = preg_replace('/\s\s+/', ' ', $text);
                        if (mb_strlen($text) < 2) {
                            return;
                        }
                        $result->add(new ArticleBodyNode($elementName, $text));
                    }

                    if ('src' === $valueType) {
                        $result->add(new ArticleBodyNode($elementName, $node->attr('src')));
                    }

                    if ('video' === $valueType) {
                        $src = $node->attr('src');
                        if (!$src) {
                            return;
                        }
                        $value = trim($src, " \t\n\r\0\x0B\/");
                        $scheme = parse_url($value, PHP_URL_SCHEME);
                        if (!$scheme) {
                            $value = 'https://' . $value;
                        }
                        $result->add(new ArticleBodyNode($elementName, $value));
                    }

                    if ('proxyJpg' === $valueType) {
                        $url = $node->attr('src');
                        if (!$url) {
                            return;
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
            );

            $description = $this->xpathParser->parseDescription($html, '//div[@class="js-post"]//p[1]')->getNodes()[0]->getValue();
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

            $selector = "//div[contains(@class, 'left_col')]";
            $articlesNode = $html->filterXPath($selector)->first();

            $articles = $articlesNode->filterXPath("//div[contains(@class, 'left_bl_item')]");

            $lastAddedPublicationTime = $this->lastPublicationTime;

            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    $pageLink = $node->filterXPath('//a')->first()->attr('href');

                    $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                    if ($this->isNeedSkipArticle($pageContent)) {
                        continue;
                    }
                    $html = new Crawler();
                    $html->addHtmlContent($pageContent->getBody()->getContents(), 'UTF-8');
                    $articleData = json_decode($html->filterXPath("//script[@type = 'application/ld+json']")->first()->text(), true);

                    if (!isset($articleData['datePublished'])) {
                        continue;
                    }
                    $publicationDate = $this->createDateFromString($articleData['datePublished']);


                    if ($publicationDate->getTimestamp() > $lastAddedPublicationTime->getTimestamp()) {
                        $title = $node->filterXPath("//div[contains(@class, 'title')] | //div[@class='right']//a ")->first()->text();
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