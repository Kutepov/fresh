<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\ru\Sobesednik;

use common\components\scrapers\common\ArticleBodyScraper;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\BaseUrls;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\XPathParserV2;
use common\components\scrapers\common\symfony\SiteParserModule\Infrastructure\SelectorsRemover;
use common\components\scrapers\common\Config;
use common\components\scrapers\dto\ArticleBody;
use common\components\scrapers\dto\ArticleItem;
use GuzzleHttp\Promise\Coroutine;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DomCrawler\Crawler;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\NewsCutter;

/**
 * Class Scraper
 * @package common\components\scrapers\sources\ru\Sobesednik
 *
 * @Config (
 * timezone="Europe/Moscow", urls={
 * "https://sobesednik.ru/avto",
 * "https://sobesednik.ru/shou-biznes",
 * "https://sobesednik.ru/dengi",
 * "https://sobesednik.ru/dmitriy-bykov",
 * "https://sobesednik.ru/kultura-i-tv",
 * "https://sobesednik.ru/nedvizhimost",
 * "https://sobesednik.ru/obshchestvo",
 * "https://sobesednik.ru/politika",
 * "https://sobesednik.ru/sport",
 * "https://sobesednik.ru/turizm",
 * "https://sobesednik.ru/zdorove-i-dom"
 * })
 */
class Scraper extends \common\components\scrapers\common\Scraper implements ArticleBodyScraper
{

    private const OVERRIDE_REPLACE_TAGS = [];

    /**
     * @var NewsCutter
     */
    private $newsCutter;

    /**
     * @var BaseUrls
     */
    private $BaseUrls;

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
        BaseUrls $BaseUrls,
        $config = []
    )
    {
        $this->selectorsRemover = $selectorsRemover;
        $this->newsCutter = $newsCutter;
        $this->XPathParser = $XPathParser;
        $this->BaseUrls = $BaseUrls;

        parent::__construct($config);
    }

    public function parseArticleBody(string $url): PromiseInterface
    {
        return $this->sendAsyncRequestWithProxy(new Request('GET', $url))->then(function (ResponseInterface $response) {
            $html = new Crawler($response->getBody()->getContents());

            $raplaceTags = self::OVERRIDE_REPLACE_TAGS;

            $selector = "//div[contains(@id, 'article-body')]";

            $textNode = $html->filterXPath($selector);

            $this->selectorsRemover->remove(
                "
            //div[contains(@class, 'grf-widget__content')]|
            //div[contains(@class, 'addpreview')]|
            //blockquote[contains(@class, 'instagram-media')]//text()|
            //blockquote[contains(@class, 'twitter-tweet')]//text()|
            //meta[contains(@content, 'images/zaglushka')]|
            //ul//li//a
            ",
                $textNode
            );

            $newsLinks = $textNode->filterXPath('//a');
            $this->newsCutter->cutterByLinks($newsLinks);

            $text = $textNode->filterXPath(
                "
            //h1[@itemprop='headline']|
            //div[@itemprop='articleBody']//img|
            //div[@itemprop='articleBody']//p|
            //div[@itemprop='articleBody']//ul|
            //div[@itemprop='articleBody']//ol|
            //div[@itemprop='articleBody']//iframe[not(contains(@src, 'www.facebook.com'))]|
            //div[@itemprop='articleBody']//blockquote[contains(@class, 'instagram-media')]|
            //div[@itemprop='articleBody']//blockquote[contains(@class, 'twitter-tweet')]//a|
            //div[@itemprop='articleBody']//blockquote[not(contains(@class, 'instagram-media')) and not(contains(@class, 'twitter-tweet'))]|
            "
            );

            $this->BaseUrls->addImageUrl('https://sobesednik.ru');
            $BaseUrls = $this->BaseUrls;

            $parse = $this->XPathParser->parse($text, $raplaceTags, $BaseUrls, true);
            $result = new ArticleBody();
            foreach ($parse->getNodes() as $node) {
                if ($node->getElementName() === 'ul' && $node->getValue()[0] === '') {
                    continue;
                }
                $result->add($node);
            }

            return $result;
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));

            $html = new Crawler($response->getBody()->getContents());

            $selector = "//div[@class='container']";
            $articlesNode = $html->filterXPath($selector);

            $articles = $articlesNode->filterXPath("//a[contains(@class, 'p-2 bg-white')]");

            $lastAddedPublicationTime = $this->lastPublicationTime;

            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    $pageLink = $node->attr('href');
                    $titleNode = $node->filterXPath("//p[@class='font-bold']|//figcaption//p")->first();

                    if (!$titleNode->count()) {
                        continue;
                    }

                    $title = $titleNode->text();

                    $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                    if ($this->isNeedSkipArticle($pageContent)) {
                        continue;
                    }
                    $html = new Crawler($pageContent->getBody()->getContents());

                    $pubDate = $html->filterXPath("//meta[@itemprop='dateModified']")->first();

                    if (!$pubDate->count()) {
                        continue;
                    }

                    $publicationDate = $this->createDateFromString($pubDate->attr('content'));

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
