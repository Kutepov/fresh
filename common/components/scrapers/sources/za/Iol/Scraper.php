<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\za\Iol;

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
 * @package common\components\scrapers\sources\za\Iol
 *
 * @Config (
 * timezone="Africa/Lusaka", urls={
 * "https://www.iol.co.za/news/africa",
 * "https://www.iol.co.za/business-report",
 * "https://www.iol.co.za/news/eish",
 * "https://www.iol.co.za/entertainment",
 * "https://www.iol.co.za/lifestyle",
 * "https://www.iol.co.za/motoring",
 * "https://www.iol.co.za/news/opinion",
 * "https://www.iol.co.za/personal-finance",
 * "https://www.iol.co.za/news/politics",
 * "https://www.iol.co.za/news/south-africa",
 * "https://www.iol.co.za/sport",
 * "https://www.iol.co.za/technology",
 * "https://www.iol.co.za/travel",
 * "https://www.iol.co.za/news/world"
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
            return $this->XPathParser->parseDescription($html, '//div[contains(@class, "lead")]//p[1]');
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//div[@id='root']";
            $articlesNode = $html->filterXPath($selector);

            $articles = $articlesNode->filterXPath("//article");
            $baseUrl = 'https://www.iol.co.za';

            $lastAddedPublicationTime = $this->lastPublicationTime;
            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    $linkNode = $node->filterXPath('//a')->first();
                    if (!$linkNode->count()) {
                        continue;
                    }
                    $pageLink = $baseUrl.$linkNode->attr('href');
                    $title = $node->filterXPath('//h1|//h4|//h6|//h5')->text();

                    $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                    if ($this->isNeedSkipArticle($pageContent)) {
                        continue;
                    }
                    $html = new Crawler();
                    $html->addHtmlContent($pageContent->getBody()->getContents(), 'UTF-8');

                    $articlePubDate = $html->filterXPath("//head//meta[@itemprop='datePublished']")->first();
                    $pubDateAttr = $articlePubDate->attr('content');
                    if (!$pubDateAttr) {
                        continue;
                    }
                    $publicationDate = $this->createDateFromString($pubDateAttr);

                    $hashPreview = $this->previewHelper->getOgImageUrlHash($html, '', 'itemprop="image"');

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
