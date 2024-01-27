<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\pl\O2Pl;

use common\components\scrapers\common\ArticleBodyScraper;
use common\components\scrapers\common\helpers\PreviewHelper;
use common\components\scrapers\common\Config;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\XPathParserV2;
use common\components\scrapers\dto\ArticleItem;
use GuzzleHttp\Promise\Coroutine;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Class Scraper
 * @package common\components\scrapers\sources\pl\O2Pl
 *
 * @Config (
 * timezone="Europe/Warsaw", urls={
 * "https://www.o2.pl/tag/ciekawostki/",
 * "https://www.o2.pl/tag/ludzie/",
 * "https://www.o2.pl/tag/plotki/",
 * "https://www.o2.pl/tag/polityka/",
 * "https://www.o2.pl/tag/sport/",
 * "https://www.o2.pl/tag/swiat/",
 * "https://www.o2.pl/tag/wydarzenia/"
 * })
 */
class Scraper extends \common\components\scrapers\common\Scraper implements ArticleBodyScraper
{
    /**
     * @var PreviewHelper
     */
    private $previewHelper;

    /**
     * @var XPathParserV2
     */
    private $XPathParser;

    public function __construct(
        PreviewHelper $previewHelper,
        XPathParserV2 $XPathParser,
        $config = []
    )
    {
        $this->previewHelper = $previewHelper;
        $this->XPathParser = $XPathParser;

        parent::__construct($config);
    }

    public function parseArticleBody(string $url): PromiseInterface
    {
        return $this->sendAsyncRequestWithProxy(new Request('GET', $url))->then(function (ResponseInterface $response) {
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');
            return $this->XPathParser->parseDescription($html, '//p[@class="sc-EHOje sc-bZQynM sc-gzVnrw gidbkT"][1]');
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//div[@id='root']";
            $articlesNode = $html->filterXPath($selector)->first();
            $articles = $articlesNode->filterXPath("//img[@data-testid='teaserImage']//ancestor::a");

            $lastAddedPublicationTime = $this->lastPublicationTime;

            $result = [];
            $baseUrl = 'https://www.o2.pl';
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {

                    if ($node->attr('href')) {
                        $pageLink = $node->attr('href');
                    } else {
                        $linkNode = $node->filterXPath('//a');
                        if (!$linkNode->count()) {
                            continue;
                        }
                        $pageLink = $linkNode->attr('href');
                    }

                    if (!filter_var($pageLink, FILTER_VALIDATE_URL)) {
                        $pageLink = $baseUrl . $pageLink;
                    }

                    $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                    if ($this->isNeedSkipArticle($pageContent)) {
                        continue;
                    }
                    $html = new Crawler();
                    $html->addHtmlContent($pageContent->getBody()->getContents(), 'UTF-8');

                    $dataArticle = $html->filterXPath(
                        "//meta[@name='article:published_time']")
                        ->first();

                    if (!$dataArticle->count()) {
                        continue;
                    }

                    $pubDateAttr = $dataArticle->attr('content');

                    $publicationDate = $this->createDateFromString($pubDateAttr);
                    $imgHash = $this->previewHelper->getOgImageUrlHash($html);
                    if ($publicationDate > $lastAddedPublicationTime) {
                        $title = $html->filterXPath('//h1')->text();
                        $result[] = new ArticleItem($pageLink, $title, $publicationDate, $imgHash);
                    }
                } catch (\Throwable $exception) {
                    $this->logArticleItemException($exception, $pageLink);
                }
            }

            yield $result;
        });
    }
}
