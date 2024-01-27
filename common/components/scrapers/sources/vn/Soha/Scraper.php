<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\vn\Soha;

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
 * @package common\components\scrapers\sources\vn\Soha
 *
 * @Config (timezone="Asia/Ho_Chi_Minh", urls={
 * "https://soha.vn/thoi-su.htm",
 * "https://soha.vn/kinh-doanh.htm",
 * "https://soha.vn/quoc-te.htm",
 * "https://soha.vn/quan-su.htm"
 * })
 */
class Scraper extends \common\components\scrapers\common\Scraper implements ArticleBodyScraper
{

    private const OVERRIDE_REPLACE_TAGS = [];

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

            $replaceTags = self::OVERRIDE_REPLACE_TAGS;

            $html = new Crawler($response->getBody()->getContents());


            $selector = "//article";

            $textNode = $html->filterXPath($selector)->first();

            $this->selectorsRemover->remove("
            //p[@class='news-info']|
            //div[contains(@class, 'share')]|
            //div[contains(@class, 'rendertop1')]
            //div[@id='admzonek1fs4xky']|
            //p[contains(@class, 'bottom-info')]|
            //ul[@id='danhsachdocthem']
            ", $textNode);

            $newsLinks = $textNode->filterXPath("//a");
            $this->newsCutter->cutterByLinks($newsLinks);

            $text = $textNode->filterXPath(
                "//ul|
            //ol|
            //blockquote[not(contains(@class, 'twitter-tweet'))]|
            //blockquote//a|
            //iframe|
            //script|
            /iframe|
            //ul|
            //ol|
            //p|
            //blockquote[not(contains(@class, 'twitter-tweet'))]|
            //blockquote//a|
            //iframe|
            //script|
            //iframe|
            //img
            "
            );

            return $this->XPathParser->parse($text, $replaceTags);
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler($pageContent->getBody()->getContents());

            $selector = "//div[@id='admWrapsite']";
            $articlesNode = $html->filterXPath($selector)->first();

            $articles = $articlesNode->filterXPath("//div[@class='shnews_box']");

            $lastAddedPublicationTime = $this->lastPublicationTime;

            $baseUrl = 'https://soha.vn';
            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {

                    $linkNode = $node->filterXPath('//h3//a')->first();
                    $pageLink = $baseUrl.$linkNode->attr('href');

                    $title = $linkNode->text();

                    $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                    if ($this->isNeedSkipArticle($pageContent)) {
                        continue;
                    }
                    $html = new Crawler($pageContent->getBody()->getContents());
                    $articleData = $html->filterXPath("//time[@class = 'op-published']")->first();

                    if (!$articleData->count()) {
                        continue;
                    }

                    $publicationDate = $this->createDateFromString(str_replace('/', '-', $articleData->text()));

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