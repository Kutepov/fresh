<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\id\Sindonews;

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
 * @package common\components\scrapers\sources\id\Sindonews
 *
 * @Config (timezone="Asia/Jakarta", urls={
 * "https://nasional.sindonews.com/",
 * "https://metro.sindonews.com/",
 * "https://ekbis.sindonews.com/",
 * "https://international.sindonews.com/",
 * "https://daerah.sindonews.com/",
 * "https://sports.sindonews.com/",
 * "https://otomotif.sindonews.com/",
 * "https://tekno.sindonews.com/",
 * "https://sains.sindonews.com/",
 * "https://lifestyle.sindonews.com/"
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
        return $this->sendAsyncRequestWithProxy(new Request('GET', $url. '?showpage=all'))->then(function (ResponseInterface $response) {
            $html = new Crawler();
            $html->addHtmlContent($this->clearHtml($response->getBody()->getContents()), 'UTF-8');

            $selector = "//div[@id='content']|//div[@class='article']|//div[@class='text-news']";

            $textNode = $html->filterXPath($selector)->first();

            $this->selectorsRemover->remove(
                "//div[@class='box-paging']|
                             //div[@class='box-outlink']",
                $textNode
            );

            $newsLinks = $textNode->filterXPath("//a");
            $this->newsCutter->cutterByLinks($newsLinks);

            $text = $textNode->filterXPath(
                "
            //img|
            //p|
            //ul|
            //ol|
            //a|
            //h4|//em|//strong||//h1
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

            $selector = "//div[@class='homelist-new']//ul|//div[@class='daerah-new']//ul";

            $articlesNode = $html->filterXPath($selector);

            $articles = $articlesNode->filterXPath("//li");

            $lastAddedPublicationTime = $this->lastPublicationTime;

            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {

                    $linkNode = $node->filterXPath("//div[@class='homelist-title']//a|//div[@class='daerah-title']//a")->first();
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
                    $publicationDate = new \DateTime($pubDateAttr);

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


    /**
     * @param string $html
     * @return string
     */
    private function clearHtml(string $html): string
    {
        return preg_replace("|<br><br>|", '</p><p>', $html);
    }

}