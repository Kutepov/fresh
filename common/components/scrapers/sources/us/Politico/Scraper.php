<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\us\Politico;

use common\components\guzzle\Guzzle;
use common\components\scrapers\common\ArticleBodyScraper;
use common\components\scrapers\common\helpers\PreviewHelper;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\XPathParserV2;
use common\components\scrapers\dto\ArticleItem;
use GuzzleHttp\Promise\Coroutine;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DomCrawler\Crawler;
use common\components\scrapers\common\Config;

/**
 * Class Scraper
 * @package common\components\scrapers\sources\us\Politico
 *
 * @Config (timezone="America/New_York", urls={
 * "https://www.politico.com/tag/education",
 * "https://www.politico.com/tag/agriculture",
 * "https://www.politico.com/cannabis",
 * "https://www.politico.com/health-care",
 * "https://www.politico.com/transportation",
 * "https://www.politico.com/technology",
 * "https://www.politico.com/finance",
 * "https://www.politico.com/news/2020-elections"
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
            return $this->XPathParser->parseDescription($html, '//p[@class="dek"]');
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler($pageContent->getBody()->getContents());

            $selector = "//section[@class='content-groupset pos-alpha']";
            $articlesNode = $html->filterXPath($selector)->first();

            $articles = $articlesNode->filterXPath("//article");

            $lastAddedPublicationTime = $this->lastPublicationTime;
            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    $pageLink = $node->filterXPath("//a")->first();
                    if (!$pageLink->count()) {
                        continue;
                    }
                    $pageLink = $pageLink->attr('href');

                    $title = $node->filterXPath("//h3|//h1")->first()->text();

                    $dateNode = $node->filterXPath("//time")->first();

                    if (!$dateNode->count()) {
                        continue;
                    }

                    $publicationDate = $this->createDateFromString($dateNode->attr('datetime'));

                    $attr = stripos($url, '2020') === false ? 'data-lazy-img' : 'src';
                    $hashPreview = $this->previewHelper->getImageUrlHashFromList($node, "//img", $attr);

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

    public function proxyEnablingAttempt(): ?int
    {
        return Guzzle::PROXY_ALWAYS_ENABLED;
    }
}