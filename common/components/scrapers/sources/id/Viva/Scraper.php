<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\id\Viva;

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
 * @package common\components\scrapers\sources\id\Viva
 *
 * @Config (timezone="Asia/Jakarta", urls={
 * "https://www.viva.co.id/berita/nasional",
 * "https://www.viva.co.id/berita/metro",
 * "https://www.viva.co.id/berita/kriminal",
 * "https://www.viva.co.id/berita/dunia",
 * "https://www.viva.co.id/berita/bisnis",
 * "https://www.viva.co.id/trending",
 * "https://www.viva.co.id/bola/liga-inggris",
 * "https://www.viva.co.id/bola/liga-italia",
 * "https://www.viva.co.id/bola/liga-spanyol",
 * "https://www.viva.co.id/bola/liga-indonesia",
 * "https://www.viva.co.id/bola/bola-sejagat",
 * "https://www.viva.co.id/bola/soccertainment",
 * "https://www.viva.co.id/sport/raket",
 * "https://www.viva.co.id/sport/onepride",
 * "https://www.viva.co.id/sport/oneprix",
 * "https://www.viva.co.id/sport/gelanggang",
 * "https://www.viva.co.id/showbiz/gosip",
 * "https://www.viva.co.id/showbiz/film",
 * "https://www.viva.co.id/showbiz/musik",
 * "https://www.viva.co.id/gaya-hidup/travel",
 * "https://www.viva.co.id/gaya-hidup/gaya",
 * "https://www.viva.co.id/gaya-hidup/kesehatan-intim",
 * "https://www.viva.co.id/otomotif/mobil",
 * "https://www.viva.co.id/otomotif/motor",
 * "https://www.viva.co.id/otomotif/tips",
 * "https://www.viva.co.id/digital/digilife",
 * "https://www.viva.co.id/digital/piranti",
 * "https://www.viva.co.id/ragam/cek-fakta",
 * "https://www.viva.co.id/ragam/round-up",
 * "https://www.viva.co.id/militer/militer-indonesia",
 * "https://www.viva.co.id/militer/militer-dunia",
 * "https://www.viva.co.id/vstory",
 * "https://www.viva.co.id/blog",
 * "https://www.viva.co.id/siaran-pers"
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
        return $this->sendAsyncRequestWithProxy(new Request('GET', $url . '?page=all&utm_medium=all-page'))->then(function (ResponseInterface $response) {
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//li[@class='left']";

            $textNode = $html->filterXPath($selector)->first();

            $newsLinks = $textNode->filterXPath("//a");
            $this->newsCutter->cutterByLinks($newsLinks);

            $this->selectorsRemover->remove("//h1[@itemprop='headline']", $textNode);

            $text = $textNode->filterXPath(
                "
                //div[@class='leading-image clearenter']//img|
            //div[@id='article-detail-content']//img|
            //div[@id='article-detail-content']//p|
            //div[@id='article-detail-content']//ul|
            //div[@id='article-detail-content']//ol|
            //div[@id='article-detail-content']//a|
            //div[@id='article-detail-content']//h4|//h1|//h2
"
            );

            $imageNodes = $textNode->filterXPath('//img');
            $isNeedPrviewImg = !$imageNodes->count();

            $result = $this->XPathParser->parse($text, null, null, $isNeedPrviewImg);

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

            $selector = "//div[@class='main-container']";

            $articlesNode = $html->filterXPath($selector);

            $articles = $articlesNode->filterXPath("//div[@class='article-list-row']");

            $lastAddedPublicationTime = $this->lastPublicationTime;


            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    $linkNode = $node->filterXPath("//span//a")->first();
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
                    $publicationDate = $this->createDateFromString($pubDateAttr, true);


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