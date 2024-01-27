<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\id\Jpnn;

use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\XPathParserV2;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\NewsCutter;
use common\components\scrapers\common\ArticleBodyScraper;
use common\components\scrapers\common\symfony\SiteParserModule\Infrastructure\SelectorsRemover;
use common\components\scrapers\dto\ArticleItem;
use GuzzleHttp\Promise\Coroutine;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use Symfony\Component\DomCrawler\Crawler;
use common\components\scrapers\common\Config;

/**
 * Class Scraper
 * @package common\components\scrapers\sources\id\Jpnn
 *
 * @Config (timezone="Asia/Jakarta", urls={
 * "https://www.jpnn.com/nasional",
 * "https://www.jpnn.com/politik",
 * "https://www.jpnn.com/daerah",
 * "https://www.jpnn.com/entertainment",
 * "https://www.jpnn.com/teknologi",
 * "https://www.jpnn.com/olahraga",
 * "https://www.jpnn.com/otomotif"
 * })
 */
class Scraper extends \common\components\scrapers\common\Scraper implements ArticleBodyScraper
{
    public const REPLACE_TAGS = [];

    /**
     * @var XPathParserV2
     */
    private $XPathParser;

    /**
     * @var NewsCutter
     */
    private $newsCutter;

    /**
     * @var SelectorsRemover
     */
    private $selectorsRemover;

    public function __construct(
        NewsCutter $newsCutter,
        XPathParserV2 $XPathParserV2,
        SelectorsRemover $selectorsRemover,
        $config = []
    )
    {
        $this->newsCutter = $newsCutter;
        $this->XPathParser = $XPathParserV2;
        $this->selectorsRemover = $selectorsRemover;

        parent::__construct($config);
    }

    public function parseArticleBody(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $result = $this->parsePage($html);

            $pagination = $html->filterXPath('//div[@class="pagination"]//a');

            if ($pagination->count() > 0) {
                for ($i = 1; $i <= $pagination->count() - 1; $i++) {
                    $page = $pagination->eq($i);
                    if ($page->text() !== 'Next') {
                        $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $page->attr('href')));
                        $html = new Crawler();
                        $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');
                        $partResult = $this->parsePage($html);
                        foreach ($partResult->getNodes() as $node) {
                            $result->add($node);
                        }
                    }
                }
            }

            yield $result;
        });
    }

    private function parsePage(Crawler $html)
    {
        $this->selectorsRemover->remove('//img[contains(@src, "i-facebook")]|
                                                     //img[contains(@src, "i-twitter")]|
                                                     //img[contains(@src, "i-pinterest")]|
                                                     //img[contains(@src, "i-linkedin")]|
                                                     //img[contains(@src, "i-whatsapp")]|
                                                     //img[contains(@src, "i-telegram")]|', $html);
        $selector = "//div[@class='page-content']";

        $textNode = $html->filterXPath($selector)->first();

        $newsLinks = $textNode->filterXPath("//a");
        $this->newsCutter->cutterByLinks($newsLinks);

        $text = $textNode->filterXPath(
            "
            //img|
            //div[@itemprop='articleBody']//img|
            //div[@itemprop='articleBody']//p|
            //div[@itemprop='articleBody']//ul|
            //div[@itemprop='articleBody']//ol|
            //div[@itemprop='articleBody']//a|
            //div[@itemprop='articleBody']//h4|
            //iframe[contains(@src, 'youtube')]
"
        );

        $imageNodes = $textNode->filterXPath('//img');
        $isNeedPrviewImg = !$imageNodes->count();

        return $this->XPathParser->parse($text, null, null, $isNeedPrviewImg);
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $contents = $pageContent->getBody()->getContents();
            $html->addHtmlContent($contents, 'UTF-8');

            $selector = "//ul[@class='content-list']";

            $articlesNode = $html->filterXPath($selector);


            $articles = $articlesNode->filterXPath("//li");

            $lastAddedPublicationTime = $this->lastPublicationTime;

            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    $linkNode = $node->filterXPath('//h1//a|//h2//a')->first();
                    if (!$linkNode->count()) {
                        continue;
                    }
                    $pageLink = $linkNode->attr('href');
                    $title = $linkNode->text();

                    $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                    if ($this->isNeedSkipArticle($pageContent)) {
                        continue;
                    }
                    $html = new Crawler();
                    $html->addHtmlContent($pageContent->getBody()->getContents(), 'UTF-8');

                    $dataArticle = $html->filterXPath("//script[contains(text(), 'datePublished')]")->first();

                    if (!$dataArticle->count()) {
                        continue;
                    }

                    $dataArticle = json_decode($dataArticle->text(), true);

                    $pubDateAttr = $this->createDateFromString($dataArticle['datePublished']);

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