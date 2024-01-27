<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\vn\Kehn14;

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
 * @package common\components\scrapers\sources\vn\Kehn14
 *
 * @Config (timezone="Asia/Ho_Chi_Minh", urls={
 * "https://kenh14.vn/star.chn",
 * "https://kenh14.vn/cine.chn",
 * "https://kenh14.vn/musik.chn",
 * "https://kenh14.vn/xa-hoi.chn",
 * "https://kenh14.vn/the-gioi.chn",
 * "https://kenh14.vn/sport.chn",
 * "https://kenh14.vn/hoc-duong.chn",
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


            $selector = "//div[@class='klw-new-content']";

            $textNode = $html->filterXPath($selector)->first();

            $this->selectorsRemover->remove("
            //div[@class='knc-menu-nav']|
            //div[@class='knc-rate-link']
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

            $selector = "//ul[contains(@class, 'knsw-list')]";
            $articlesNode = $html->filterXPath($selector)->first();

            $articles = $articlesNode->filterXPath("//li[contains(@class, 'knswli')]");

            $lastAddedPublicationTime = $this->lastPublicationTime;

            $baseUrl = 'https://kenh14.vn';
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
                    $articleData = json_decode($html->filterXPath("//script[@type = 'application/ld+json']")->first()->text());

                    $articlePubDate = $articleData->datePublished;

                    if (!$articlePubDate) {
                        continue;
                    }

                    $publicationDate = $this->createDateFromString($articlePubDate);

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