<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\ua\Pravda\uk;

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
 * @package common\components\scrapers\sources\ua\Pravda\uk
 *
 * @Config (timezone="Europe/Kiev", urls={
 * "https://www.pravda.com.ua/news/"
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
                'elementName' => 'paragraph',
            ],
        ],
        'h2' => [
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

            $pageContent =  $response->getBody()->getContents();

            $html = new Crawler();
            $html->addHtmlContent($pageContent, 'UTF-8');


            $selector = "//meta[@property='og:image']|//article[@class='post']";

            $textNode = $html->filterXPath($selector);
            $this->selectorsRemover->remove(
                "
            //p[not(*)][not(normalize-space())]|
            //p[contains(text(), 'Читайте детальніше:')]|
            //p[contains(text(), 'Читайте подробнее:')]|
            //p[contains(text(), 'Вас також може зацікавити:')]|
            //p[contains(text(), 'Вас также может заинтересовать:')]|
            //p[contains(text(), 'Знати більше:')]|
            //div[contains(@class, 'post_text')]//p/strong/em[contains(text(), 'Читайте також')]|
            //div[contains(@class, 'post_text')]//p/strong/em[contains(text(), 'Читайте также')]|
            //div[contains(@class, 'post_text')]//p/strong/em[contains(text(), 'Знати більше')]|
            //div[contains(@class, 'post_text')]//p/strong/em[contains(text(), 'Знати більше')]//following::p[1]|
            //div[contains(@class, 'post_text')]//p/strong/em[contains(text(), 'Знать больше')]|
            //div[contains(@class, 'post_text')]//p/strong/em[contains(text(), 'Знать больше')]//following::p[1]
            ",
                $textNode
            );

            $newsLinks = $textNode->filterXPath("//div[contains(@class, 'post_text')]//a");
            $this->newsCutter->cutterByLinks($newsLinks);

            $text = $textNode->filterXPath(
                "
            //meta|
            //div[contains(@class, 'post__text')]//ul|
            //div[contains(@class, 'post__text')]//ol|
            //div[contains(@class, 'post__text')]/p|
            //div[contains(@class, 'post__text')]//blockquote[not(contains(@class, 'twitter-tweet'))]|
            //div[contains(@class, 'post__text')]//blockquote//a|
            //div[contains(@class, 'post__text')]//iframe|
            //div[contains(@class, 'post__text')]//script|                        
            //div[contains(@class, 'post_text')]//ul|
            //div[contains(@class, 'post_text')]//ol|
            //div[contains(@class, 'post_text')]/p|
            //div[contains(@class, 'post_text')]//blockquote[not(contains(@class, 'twitter-tweet'))]|
            //div[contains(@class, 'post_text')]//blockquote//a|
            //div[contains(@class, 'post_text')]//iframe|
            //div[contains(@class, 'post_text')]//script|
            //div[contains(@class, 'post__video')]/iframe|
            //div[contains(@class, 'post_photo_news')]/img|
            //div[contains(@class, 'image-box')]//img
            "
            );

            $replaceTags = self::REPLACE_TAGS;
            $result = $this->XPathParser->parse($text, $replaceTags, null, true, null, true, true);

            $description = $this->XPathParser->parseDescription($html, '//div[@class="post_text"]//p[1]')->getNodes()[0]->getValue();
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

            $selector = "//div[contains(@class, 'container_sub_news_list_wrapper')]";
            $articlesNode = $html->filterXPath($selector)->first();

            $articles = $articlesNode->filterXPath("//div[contains(@class, 'article_news_list')]");

            $lastAddedPublicationTime = $this->lastPublicationTime;

            $baseUrl = 'https://www.pravda.com.ua';

            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {

                    $linkNode = $node->filterXPath('//div[@class="article_header"]//a')->first();
                    $this->selectorsRemover->remove('//em', $linkNode);

                    if (stripos($linkNode->attr('href'), 'http') !== false) {
                        continue;
                    }

                    $pageLink = $baseUrl.$linkNode->attr('href');
                    $title = $linkNode->text();

                    $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                    if ($this->isNeedSkipArticle($pageContent)) {
                        continue;
                    }
                    $html = new Crawler();
                    $html->addHtmlContent(mb_convert_encoding($pageContent->getBody()->getContents(), 'utf-8', 'windows-1251'), 'UTF-8');
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
}