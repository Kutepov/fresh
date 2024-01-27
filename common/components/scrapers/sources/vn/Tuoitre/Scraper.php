<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\vn\Tuoitre;

use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\XPathParserV2;
use common\components\scrapers\common\services\HashImageService;
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
 * @package common\components\scrapers\sources\vn\Tuoitre
 *
 * @Config (timezone="Asia/Ho_Chi_Minh", urls={
 * "https://tuoitre.vn/thoi-su.htm",
 * "https://tuoitre.vn/the-gioi.htm",
 * "https://tuoitre.vn/phap-luat.htm",
 * "https://tuoitre.vn/kinh-doanh.htm",
 * "https://tuoitre.vn/xe.htm",
 * "https://dulich.tuoitre.vn/",
 * "https://thethao.tuoitre.vn/",
 * "https://tuoitre.vn/giao-duc.htm",
 * "https://tuoitre.vn/khoa-hoc.htm",
 * "https://tuoitre.vn/suc-khoe.htm"
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
     * @var HashImageService
     */
    private $hashImageService;

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
        HashImageService $hashImageService,
        NewsCutter $newsCutter,
        XPathParserV2 $XPathParser,
        $config = []
    )
    {
        $this->selectorsRemover = $selectorsRemover;
        $this->hashImageService = $hashImageService;
        $this->XPathParser = $XPathParser;
        $this->newsCutter = $newsCutter;

        parent::__construct($config);
    }

    public function parseArticleBody(string $url): PromiseInterface
    {
        return $this->sendAsyncRequestWithProxy(new Request('GET', $url))->then(function (ResponseInterface $response) {

            $replaceTags = self::OVERRIDE_REPLACE_TAGS;

            $html = new Crawler($response->getBody()->getContents());


            $selector = "//div[@class='main-content-body']";

            $textNode = $html->filterXPath($selector)->first();

            $newsLinks = $textNode->filterXPath("//a");
            $this->newsCutter->cutterByLinks($newsLinks);

            $this->selectorsRemover->remove("
            //div[@type='RelatedOneNews']|
            //div[@id='wrapper-image-pos-sponsor']|
            //div[@class='author']
            
            ", $textNode);

            $text = $textNode->filterXPath(
                "//ul|
                //h2|
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

            $selector = "//ul[@class='list-news-content']|//div[@class='page_col_left']";
            $articlesNode = $html->filterXPath($selector)->first();

            $articles = $articlesNode->filterXPath("//li[@class='news-item']|//li");
            $lastAddedPublicationTime = $this->lastPublicationTime;
            $baseUrl = 'https://tuoitre.vn';
            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {

                    $linkNode = $node->filterXPath('//div[@class="name-news"]//a|//h3//a')->first();
                    $pageLink = $baseUrl.$linkNode->attr('href');

                    $title = $linkNode->text();

                    $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                    if ($this->isNeedSkipArticle($pageContent)) {
                        continue;
                    }
                    $html = new Crawler($pageContent->getBody()->getContents());

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