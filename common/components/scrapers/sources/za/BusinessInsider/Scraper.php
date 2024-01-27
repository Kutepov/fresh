<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\za\BusinessInsider;

use common\components\scrapers\common\ArticleBodyScraper;
use  common\components\scrapers\common\helpers\PreviewHelper;
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
 * @package common\components\scrapers\sources\za\BusinessInsider
 *
 * @Config (
 * timezone="Africa/Lusaka", urls={
 * "https://www.businessinsider.co.za/business",
 * "https://www.businessinsider.co.za/executive",
 * "https://www.businessinsider.co.za/life",
 * "https://www.businessinsider.co.za/Money-And-Markets",
 * "https://www.businessinsider.co.za/Tech",
 * "https://www.businessinsider.co.za/travel",
 * "https://www.businessinsider.co.za/trending"
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
            $html->addHtmlContent($response->getBody()->getContents());
            return $this->XPathParser->parseDescription($html, '//div[contains(@class, "article__body")]//p[1]');
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//div[contains(@class, 'tf-lhs-col')]|//div[contains(@class, 'article-listing')]";
            $articlesNode = $html->filterXPath($selector);

            $articles = $articlesNode->filterXPath("//article");
            $lastAddedPublicationTime = $this->lastPublicationTime;

            $result = [];
            $baseUrl = 'https://www.businessinsider.co.za';
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    $linkNode = $node->filterXPath("//a[contains(@class, 'article-item')]")->first();
                    $pageLink = $baseUrl . $linkNode->attr('href');
                    $title = $node->filterXPath("//a[contains(@class, 'article-item')]//span")->text();

                    $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                    if ($this->isNeedSkipArticle($pageContent)) {
                        continue;
                    }

                    $html = new Crawler();
                    $html->addHtmlContent($pageContent->getBody()->getContents(), 'UTF-8');
                    $articlePubDateNode = $html->filterXPath("//meta[@name='publisheddate']");
                    if (!$articlePubDateNode->count()) {
                        continue;
                    }
                    $pubDateAttr = $articlePubDateNode->attr('content');

                    $publicationDate = $this->createDateFromString($pubDateAttr);

                    $hashPreview = $this->previewHelper->getOgImageUrlHash($html);

                    if ($publicationDate > $lastAddedPublicationTime) {
                        $result[] = new ArticleItem($pageLink, $title, $publicationDate, $hashPreview);
                    }
                } catch (\Throwable $exception) {
                    $this->logArticleItemException($exception, $pageLink);
                }
            }

            yield $result;
        });
    }
}
