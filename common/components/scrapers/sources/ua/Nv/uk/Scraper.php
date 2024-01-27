<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\ua\Nv\uk;

use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\XPathParserV2;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\NewsCutter;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\SelectorsRemover;
use common\components\scrapers\common\ArticleBodyScraper;
use common\components\scrapers\dto\ArticleItem;
use GuzzleHttp\Promise\Coroutine;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DomCrawler\Crawler;
use common\components\scrapers\common\Config;

/**
 * Class Scraper
 * @package common\components\scrapers\sources\ua\Nv\uk
 *
 * @Config (timezone="Europe/Kiev", urls={
 * "https://life.nv.ua/ukr/znamenitosti.html",
 * "https://nv.ua/ukr/art.html",
 * "https://nv.ua/ukr/auto.html",
 * "https://nv.ua/ukr/biz.html",
 * "https://nv.ua/ukr/biz/economics.html",
 * "https://nv.ua/ukr/health.html",
 * "https://nv.ua/ukr/kyiv.html",
 * "https://nv.ua/ukr/sport.html",
 * "https://nv.ua/ukr/techno.html",
 * "https://nv.ua/ukr/ukraine.html",
 * "https://nv.ua/ukr/ukraine/events.html",
 * "https://nv.ua/ukr/ukraine/politics.html",
 * "https://nv.ua/ukr/world.html"
 * })
 */
class Scraper extends \common\components\scrapers\common\Scraper implements ArticleBodyScraper
{

    private const SKIP_NEWS_LABELS = [
        'Новини компаній',
        'НВ Преміум',
        'Ексклюзив НВ',
    ];

    private const REPLACE_TAGS = [
        'a' => [
            [
                'contains' => 'status',
                'attribute' => 'href',
                'valueType' => 'href',
                'elementName' => 'twitter',
            ],
            [
                'contains' => 'youtube.com',
                'attribute' => 'href',
                'valueType' => 'youtube-video-id',
                'elementName' => 'video',
            ],
            [
                'contains' => 'www.facebook.com',
                'attribute' => 'href',
                'valueType' => 'video',
                'elementName' => 'facebook',
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
            [
                'contains' => 't.co/',
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
        'amp-facebook' => [
            [
                'attribute' => 'data-href',
                'elementName' => 'facebook',
                'valueType' => 'amp-facebook',
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
                'valueType' => 'data-telegram-post',
                'elementName' => 'telegram',
            ],
        ]
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

    public function __construct(
        SelectorsRemover $selectorsRemover,
        NewsCutter $newsCutter,
        XPathParserV2 $XPathParser,
        $config = []
    )
    {
        $this->selectorsRemover = $selectorsRemover;
        $this->XPathParser = $XPathParser;
        $this->newsCutter = $newsCutter;

        parent::__construct($config);
    }

    public function parseArticleBody(string $url): PromiseInterface
    {
        return $this->sendAsyncRequestWithProxy(new Request('GET', $url))->then(function (ResponseInterface $response) {
            $html = new Crawler($response->getBody()->getContents());

            $selector = '//article[1]|//div[@class="article_body"]';

            $textNode = $html->filterXPath($selector)->first();

            $this->selectorsRemover->remove(
                "//div[contains(@class, 'media__also__news_link')]|
            //div[contains(@class, 'article__content_   _head_img-info')]|
            //div[contains(@class, 'coronavirus_subscribe')]|
            //div[contains(@class, 'media__gallery')]|
            //p[contains(text(), 'От') and contains(text(), 'редактора:')]/following-sibling::*|
            //p[contains(text(), 'От') and contains(text(), 'редактора:')]|
            //div[contains(@class, 'opinion_author')]|
            //div[@class='paywall-area']|
            //p//b//i|
            //i[contains(text(), 'Текст опубліковано з')]/../following-sibling::*|
            //i[contains(text(), 'Текст опубліковано з')]/..|
            //div[contains(@class, 'article__content__footer')]|
            //div[contains(@class, 'media__also__opinions')]|
            //a[contains(text(), 'Оригинал')]|
            //div[@class='media__also__news__container']|
            //div[@class='longread_tags']|
            //div[@class='article__content__head__text']|
            //p[contains(text(), 'Приєднуйтесь до нас у соцмережах')]|
            //div[@class='article__tags  red  ']|
            //img[@class='logo']|
            //div[@class='print_magazine_promo_new']
            ",
                $textNode
            );

            $newsLinks = $textNode->filterXPath("//div[contains(@class, 'content_wrapper')]//a");
            $this->newsCutter->cutterByLinks($newsLinks);

            $text = $textNode->filterXPath(
                "
            //div[contains(@class, 'content_wrapper')]//img|
            //figure[contains(@class, 'article__content__head')]//amp-img/amp-img|
            //header[contains(@class, 'article__content__head_img')]//ing[1]|
            //div[contains(@class, 'content_wrapper')]//p[not(blockquote[contains(@class, 'twitter-tweet')])]|
            //div[contains(@class, 'content_wrapper')]//iframe[contains(@src, 'youtube.com')]|
            //div[contains(@class, 'content_wrapper')]//ul|
            //div[contains(@class, 'content_wrapper')]//iframe[contains(@src, 'facebook.com')]|
            //div[contains(@class, 'media__photo')]//amp-img/amp-img|
            //div[contains(@class, 'media__photo')]//div[contains(@class, 'media__photo__container__text--source')]|
            //div[contains(@class, 'media__embed')]//iframe[contains(@src, 'facebook.com')]|
            //blockquote|
            //p|
            //picture//img|
            //amp-youtube|
            //amp-facebook|
            //script[contains(@src, 'telegram')]|
            //a[contains(@href, 't.co')]|"
            );

            $result = $this->XPathParser->parse($text, self::REPLACE_TAGS, null, false, null, true, true);

            $description = $this->XPathParser->parseDescription($html, '//div[@class="subtitle"]//p[1]')->getNodes()[0]->getValue();
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

            $selector = "//div[@class='main-wrapper']";
            $articlesNode = $html->filterXPath($selector)->first();

            $articles = $articlesNode->filterXPath("//a[@class='atom-wrapper-body']|
                                                          //a[@class='row-result-body']");

            $lastAddedPublicationTime = $this->lastPublicationTime;

            $baseUrl = 'https://nv.ua';

            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                $this->selectorsRemover->remove('//div[@class="atom__opinion__author"]|
                                                              //div[@class="atom__text__main_sub red"]|
                                                              //div[@class="widget promo_youtube full-width"]|
                                                              //div[contains(@class, "atom-text-sub")]',
                    $node);

                if ($node->text() === '') {
                    continue;
                }
                try {

                    $pageLink = $node->attr('href');

                    if (stripos($pageLink, 'vakansiya') !== false) {
                        continue;
                    }

                    if (!filter_var($pageLink, FILTER_VALIDATE_URL)) {
                        $pageLink = $baseUrl . $pageLink;
                    }
                    $title = $node->filterXPath("//div[@class='text']|
                                                        //div[@class='atom-text']//div|
                                                        //div[@class='title ']")->first()->text();

                    $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));

                    if ($this->isNeedSkipArticle($pageContent)) {
                        continue;
                    }

                    $html = new Crawler($pageContent->getBody()->getContents());

                    $specLabel = $html->filterXPath("//div[contains(@class, 'spec-label spec-label--blue')]|//div[contains(@class, 'spec-label spec-label--paywall_label')]")->first();
                    $specText = $specLabel->count() ? $specLabel->text() : null;
                    $isExclusive = $html->filterXPath('//div[@class="spec-label spec-label--newtime_label"]')->count();
                    if (in_array($specText, self::SKIP_NEWS_LABELS) || $isExclusive) {
                        continue;
                    }

                    $articleDataNode = $html->filterXPath("//script[@type = 'application/ld+json']")->first();

                    if (!$articleDataNode->count()) {
                        $articlePubDateNode = $html->filterXPath("//article[@class='article__content new_content']//div")->first();

                        if (!$articlePubDateNode->count()) {
                            continue;
                        }
                        $publicationDate = $this->createDateFromString($articlePubDateNode->attr('data-article-published-at'));

                    }
                    else {
                        $articlePubDate = json_decode($articleDataNode->text())[3]->datePublished;
                        $publicationDate = $this->createDateFromString($articlePubDate);
                    }

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