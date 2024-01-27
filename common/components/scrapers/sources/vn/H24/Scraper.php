<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\vn\H24;

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
 * @package common\components\scrapers\sources\vn\H24
 *
 * @Config (timezone="Asia/Ho_Chi_Minh", urls={
 * "https://www.24h.com.vn/tin-tuc-trong-ngay-c46.html",
 * "https://www.24h.com.vn/bong-da-c48.html",
 * "https://www.24h.com.vn/tin-tuc-quoc-te-c415.html",
 * "https://www.24h.com.vn/thoi-trang-c78.html",
 * "https://www.24h.com.vn/an-ninh-hinh-su-c51.html",
 * "https://www.24h.com.vn/thoi-trang-hi-tech-c407.html",
 * "https://www.24h.com.vn/cach-phong-tranh-dich-covid-19-c62e6065.html",
 * "https://www.24h.com.vn/kinh-doanh-c161.html",
 * "https://www.24h.com.vn/clip-1-phut-bong-da-24h-c48e6106.html",
 * "https://www.24h.com.vn/am-thuc-c460.html",
 * "https://www.24h.com.vn/lam-dep-c145.html",
 * "https://www.24h.com.vn/doi-song-showbiz-c729.html",
 * "https://www.24h.com.vn/giai-tri-c731.html",
 * "https://www.24h.com.vn/ban-tre-cuoc-song-c64.html",
 * "https://www.24h.com.vn/giao-duc-du-hoc-c216.html",
 * "https://www.24h.com.vn/the-thao-c101.html",
 * "https://www.24h.com.vn/phi-thuong-ky-quac-c159.html",
 * "https://www.24h.com.vn/cong-nghe-thong-tin-c55.html",
 * "https://www.24h.com.vn/o-to-c747.html"
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


            $selector = "//article[@id='article_body']";

            $textNode = $html->filterXPath($selector)->first();

            $newsLinks = $textNode->filterXPath("//a");
            $this->newsCutter->cutterByLinks($newsLinks);

            $this->selectorsRemover->remove("
            //p[@class='linkOrigin']|
            //div[@id='zone_banner_sponser_product']|
            //div[@class='bv-lq']|
            //div[@class='sbNws']|
            //a[contains(@onclick,'gtag(')]
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

            $selector = "//section[contains(@class, 'enter-24h-cate-page')]|//div[@class='brkNs']";
            $articlesNode = $html->filterXPath($selector)->first();

            $articles = $articlesNode->filterXPath("//article");

            $lastAddedPublicationTime = $this->lastPublicationTime;

            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {

                    $linkNode = $node->filterXPath('//a')->first();
                    $pageLink = $linkNode->attr('href');

                    $title = $linkNode->text();

                    $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                    if ($this->isNeedSkipArticle($pageContent)) {
                        continue;
                    }
                    $html = new Crawler($pageContent->getBody()->getContents());

                    $articlePubDate = $html->filterXPath("//head//meta[@itemprop='datePublished']")->first();
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