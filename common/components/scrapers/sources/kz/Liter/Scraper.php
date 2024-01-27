<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\kz\Liter;

use common\components\scrapers\common\ArticleBodyScraper;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\XPathParserV2;
use common\components\scrapers\common\symfony\SiteParserModule\Infrastructure\SelectorsRemover;
use common\components\scrapers\common\Config;
use common\components\scrapers\dto\ArticleItem;
use GuzzleHttp\Promise\Coroutine;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use Symfony\Component\DomCrawler\Crawler;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\NewsCutter;

/**
 * Class Scraper
 * @package common\components\scrapers\sources\kz\Liter
 *
 * @Config (
 * timezone="Asia/Almaty", urls={
 * "https://liter.kz/glavnye-novosti/",
 * "https://liter.kz/kazakhstan-news/",
 * "https://liter.kz/world-news/",
 * "https://liter.kz/policy/",
 * "https://liter.kz/economy/",
 * "https://liter.kz/exclusive/",
 * "https://liter.kz/sport/"
 * })
 */
class Scraper extends \common\components\scrapers\common\Scraper implements ArticleBodyScraper
{
    private const REPLACE_TAGS = [
        'div' => [
            [
                'contains' => 'youtube.com',
                'attribute' => 'data-src',
                'valueType' => 'video',
                'elementName' => 'video',
            ],
        ],
    ];

    /**
     * @var NewsCutter
     */
    private $newsCutter;

    /**
     * @var XPathParserV2
     */
    private $XPathParser;


    /**
     * @var SelectorsRemover
     */
    private $selectorsRemover;

    public function __construct(
        NewsCutter $newsCutter,
        SelectorsRemover $selectorsRemover,
        XPathParserV2 $XPathParser,
        $config = []
    )
    {
        $this->selectorsRemover = $selectorsRemover;
        $this->newsCutter = $newsCutter;
        $this->XPathParser = $XPathParser;

        parent::__construct($config);
    }

    public function parseArticleBody(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));

            $replaceTags = self::REPLACE_TAGS;

            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//div[@class='row']//div[@class='col-8']";

            $textNode = $html->filterXPath($selector);

            $this->selectorsRemover->remove(
                "
            //div[contains(@class, 'jnews_inline_related_post_wrapper')]|//img[contains(@src, 'svg')]|
            //div[contains(@class, 'entry-content')]//div[contains(@class, 'content-inner')]//div[contains(@class, 'fb-post')]//text()|
            //div[contains(@class, 'entry-content')]//div[contains(@class, 'content-inner')]//blockquote[contains(@class, 'twitter-tweet')]//text()|
            //div[contains(@class, 'entry-content')]//div[contains(@class, 'content-inner')]//blockquote[contains(@class, 'instagram-media')]//text()|
            ",
                $textNode
            );

            $newsLinks = $textNode->filterXPath("//div[contains(@class, 'news__text')]//a");
            $this->newsCutter->cutterByLinks($newsLinks);

            $text = $textNode->filterXPath(
                "
            //div[contains(@class, 'xl-mb-20')]//img|
            //div[@data-type='youtube']|
            //div[contains(@class, 'youtube-player')]|
            //div[contains(@class, 'news__text')]//p[not(ancestor::blockquote)]|
            //div[contains(@class, 'news__text')]//ul|
            //div[contains(@class, 'news__text')]//ol|
            //div[contains(@class, 'news__text')]//blockquote|
            //blockquote[contains(@class, 'instagram-media')]|
            //div[contains(@class, 'news__text')]//blockquote[contains(@class, 'twitter-tweet')]//p/a|
            //div[contains(@class, 'news__text')]//div[contains(@class, 'fb-post')]|
            //div[contains(@class, 'news__text')]//figure/img|
            //div[contains(@class, 'news__text')]//img|
            "
            );

            yield $this->XPathParser->parse($text, $replaceTags);
        });
    }


    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));

            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//div[@class='col-8 category']";
            $articlesNode = $html->filterXPath($selector)->first();

            $articles = $articlesNode->filterXPath("//div[contains(@class, 'news news--border-bottom')]");
            $lastAddedPublicationTime = $this->lastPublicationTime;
            $result = [];

            $baseUrl = 'https://liter.kz';
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    $linkNode = $node->filterXPath("//h3//a");
                    $pageLink = $baseUrl . $linkNode->attr('href');
                    $title = $linkNode->text();

                    $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                    if ($this->isNeedSkipArticle($pageContent)) {
                        continue;
                    }
                    $html = new Crawler();
                    $html->addHtmlContent($pageContent->getBody()->getContents(), 'UTF-8');

                    $dataArticle = $html->filterXPath(
                        "//meta[@property='article:published_time']")
                        ->first();

                    if (!$dataArticle->count()) {
                        continue;
                    }

                    $pubDateAttr = $dataArticle->attr('content');

                    $publicationDate = $this->createDateFromString($pubDateAttr);

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

}
