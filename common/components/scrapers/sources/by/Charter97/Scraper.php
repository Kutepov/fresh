<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\by\Charter97;

use common\components\scrapers\common\ArticleBodyScraper;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\NewsCutter;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\XPathParserV2;
use common\components\scrapers\common\symfony\SiteParserModule\Infrastructure\SelectorsRemover;
use common\components\scrapers\common\services\HashImageService;
use common\components\scrapers\common\Config;
use common\components\scrapers\dto\ArticleItem;
use GuzzleHttp\Promise\Coroutine;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Class Scraper
 * @package common\components\scrapers\sources\by\Charter97
 *
 * @Config (
 * timezone="Europe/Minsk", urls={
 * "https://charter97.org/ru/news/auto/",
 * "https://charter97.org/ru/news/culture/",
 * "https://charter97.org/ru/news/economics/",
 * "https://charter97.org/ru/news/events/",
 * "https://charter97.org/ru/news/health/",
 * "https://charter97.org/ru/news/history/",
 * "https://charter97.org/ru/news/hi-tech/",
 * "https://charter97.org/ru/news/interview/",
 * "https://charter97.org/ru/news/leisure/",
 * "https://charter97.org/ru/news/opinion/",
 * "https://charter97.org/ru/news/politics/",
 * "https://charter97.org/ru/news/society/",
 * "https://charter97.org/ru/news/sport/"
 * })
 */
class Scraper extends \common\components\scrapers\common\Scraper implements ArticleBodyScraper
{
    private const OVERRIDE_REPLACE_TAGS = [];

    /**
     * @var HashImageService
     */
    private $hashImageService;

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
        HashImageService $hashImageService,
        SelectorsRemover $selectorsRemover,
        XPathParserV2 $XPathParserV2,
        NewsCutter $newsCutter,
        $config = []
    )
    {
        $this->hashImageService = $hashImageService;
        $this->selectorsRemover = $selectorsRemover;
        $this->XPathParser = $XPathParserV2;
        $this->newsCutter = $newsCutter;

        parent::__construct($config);
    }


    public function parseArticleBody(string $url): PromiseInterface
    {
        return $this->sendAsyncRequestWithProxy(new Request('GET', $url))->then(function (ResponseInterface $response) {
            $raplaceTags = self::OVERRIDE_REPLACE_TAGS;
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//article[@class = 'article']";

            $textNode = $html->filterXPath($selector);

            $this->selectorsRemover->remove(
                "
            //header[contains(@class, 'article__header')]|
            //blockquote[contains(@class, 'instagram-media')]//text()|
            //blockquote[contains(@class, 'twitter-tweet')]//text()|
            //div[@id='donate-banner']
            ",
                $textNode
            );

            $newsLinks = $textNode->filterXPath('//a');
            $this->newsCutter->cutterByLinks($newsLinks);

            $text = $textNode->filterXPath(
                "
            //img|
            //p|
            //ul|
            //ol|
            //iframe|
            //blockquote[contains(@class, 'instagram-media')]|
            //blockquote[contains(@class, 'twitter-tweet')]//a|
            "
            );

            return $this->XPathParser->parse($text, $raplaceTags, null, true);
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//div[contains(@class, 'main-wrap')]//div[contains(@class, 'news_latest')]//ul";
            $articlesNode = $html->filterXPath($selector);

            $articles = $articlesNode->filterXPath('//li');

            $baseUrl = 'https://charter97.org';

            $lastAddedPublicationTime = $this->lastPublicationTime;
            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    $linkNode = $node->filterXPath('//a')->first();
                    $pageLink = $baseUrl.$linkNode->attr('href');
                    $title = $linkNode->filterXPath('//strong[contains(@class, "news__title")]')->text();

                    $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                    if ($this->isNeedSkipArticle($pageContent)) {
                        continue;
                    }
                    $html = new Crawler();
                    $html->addHtmlContent($pageContent->getBody()->getContents(), 'UTF-8');

                    $articlePubDate = $html->filterXPath("//ul[contains(@class, 'article__info')]/li[1]")->text();

                    if ($articlePubDate) {
                        try {
                            $publicationDate = $this->createDateFromString($articlePubDate);
                        }
                        catch (\Exception $e) {
                            $articlePubDate = $html->filterXPath("//ul[contains(@class, 'article__info')]/li")->eq(1)->text();
                            $publicationDate = $this->createDateFromString($articlePubDate);
                        }
                    } else {
                        continue;
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
