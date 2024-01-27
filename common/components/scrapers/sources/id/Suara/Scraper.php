<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\id\Suara;

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
 * @package common\components\scrapers\sources\id\Suara
 *
 * @Config (timezone="Asia/Jakarta", urls={
 * "https://www.suara.com/news/news-category/nasional",
 * "https://www.suara.com/news/news-category/internasional",
 * "https://www.suara.com/bisnis/bisnis-category/makro",
 * "https://www.suara.com/bisnis/bisnis-category/keuangan",
 * "https://www.suara.com/bisnis/bisnis-category/properti",
 * "https://www.suara.com/bisnis/bisnis-category/inspiratif",
 * "https://www.suara.com/bola/bola-category/liga-inggris",
 * "https://www.suara.com/bola/bola-category/liga-spanyol",
 * "https://www.suara.com/bola/bola-category/bola-dunia",
 * "https://www.suara.com/bola/bola-category/bola-indonesia",
 * "https://www.suara.com/sport/sport-category/raket",
 * "https://www.suara.com/sport/sport-category/balap",
 * "https://www.suara.com/sport/sport-category/arena",
 * "https://www.suara.com/lifestyle/lifestyle-category/female",
 * "https://www.suara.com/lifestyle/lifestyle-category/male",
 * "https://www.suara.com/lifestyle/lifestyle-category/relationship",
 * "https://www.suara.com/lifestyle/lifestyle-category/food-travel",
 * "https://www.suara.com/lifestyle/lifestyle-category/komunitas",
 * "https://www.suara.com/entertainment/entertainment-category/gosip",
 * "https://www.suara.com/entertainment/entertainment-category/music",
 * "https://www.suara.com/entertainment/entertainment-category/film",
 * "https://www.suara.com/otomotif/otomotif-category/mobil",
 * "https://www.suara.com/otomotif/otomotif-category/motor",
 * "https://www.suara.com/otomotif/otomotif-category/autoseleb",
 * "https://www.suara.com/tekno/tekno-category/internet",
 * "https://www.suara.com/tekno/tekno-category/tekno",
 * "https://www.suara.com/tekno/tekno-category/sains",
 * "https://www.suara.com/tekno/tekno-category/game",
 * "https://www.suara.com/health/health-category/women",
 * "https://www.suara.com/health/health-category/men",
 * "https://www.suara.com/health/health-category/parenting",
 * "https://www.suara.com/health/health-category/konsultasi"
 * })
 */
class Scraper extends \common\components\scrapers\common\Scraper implements ArticleBodyScraper
{

    public const REPLACE_TAGS = [];

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
        XPathParserV2 $XPathParserV2,
        $config = []
    )
    {
        $this->selectorsRemover = $selectorsRemover;
        $this->newsCutter = $newsCutter;
        $this->XPathParser = $XPathParserV2;

        parent::__construct($config);
    }

    public function parseArticleBody(string $url): PromiseInterface
    {
        return $this->sendAsyncRequestWithProxy(new Request('GET', $url . '?page=all'))->then(function (ResponseInterface $response) {
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//meta[@property='og:image']|//article[@itemprop='articleBody']";

            $textNode = $html->filterXPath($selector);

            $this->selectorsRemover->remove(
                "//ul[@class='pagination']|
                              //p[@class='baca-juga-new']|
                              //noscript|
                              //script",
                $textNode
            );

            $newsLinks = $textNode->filterXPath("//a");
            $this->newsCutter->cutterByLinks($newsLinks);

            $text = $textNode->filterXPath(
                "
            //meta|
            //img|
            //img|
            //p|
            //ul|
            //ol|
            //a|
            //h4
"
            );

            $result = $this->XPathParser->parse($text, null, null, true);

            return $result;
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $contents = $pageContent->getBody()->getContents();
            $html->addHtmlContent($contents, 'UTF-8');

            $selector = "//div[@id='main-content']//ul";

            $articlesNode = $html->filterXPath($selector);


            $articles = $articlesNode->filterXPath("//li");

            $lastAddedPublicationTime = $this->lastPublicationTime;

            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {

                    $linkNode = $node->filterXPath('//h4//a')->first();
                    $pageLink = $linkNode->attr('href');
                    $title = $linkNode->text();

                    $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                    if ($this->isNeedSkipArticle($pageContent)) {
                        continue;
                    }
                    $html = new Crawler();
                    $html->addHtmlContent($pageContent->getBody()->getContents(), 'UTF-8');

                    $articlePubDate = $html->filterXPath("//head//meta[@property='article:published_time']")->first();
                    $pubDateAttr = $articlePubDate->attr('content');
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
}