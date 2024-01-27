<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\us\Latimes;

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
 * @package common\components\scrapers\sources\us\Latimes
 *
 * @Config (timezone="America/New_York", urls={
 * "https://www.latimes.com/homeless-housing",
 * "https://www.latimes.com/sports",
 * "https://www.latimes.com/world-nation",
 * "https://www.latimes.com/science",
 * "https://www.latimes.com/politics",
 * "https://www.latimes.com/environment",
 * "https://www.latimes.com/business/autos",
 * "https://www.latimes.com/business/real-estate",
 * "https://www.latimes.com/business/technology",
 * "https://www.latimes.com/business"
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
            return $this->XPathParser->parseDescription($html, '//div[@class="page-article-body"]//p[1]');
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler($pageContent->getBody()->getContents());

            $selector = "//div[@class='page-ad-margins']";
            $articlesNode = $html->filterXPath($selector)->first();

            $articles = $articlesNode->filterXPath("//div[@class='promo-wrapper']");

            $lastAddedPublicationTime = $this->lastPublicationTime;

            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    $linkNode = $node->filterXPath('//*[@class="promo-title"]//a')->first();
                    if ($linkNode->count() === 0) {
                        continue;
                    }
                    $pageLink = $linkNode->attr('href');

                    $title = $linkNode->text();

                    $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                    if ($this->isNeedSkipArticle($pageContent)) {
                        continue;
                    }
                    $html = new Crawler($pageContent->getBody()->getContents());

                    $dataArticle = $html->filterXPath("//script[@type='application/ld+json']")->first();

                    if (!$dataArticle->count()) {
                        continue;
                    }

                    $dataArticle = json_decode($dataArticle->text(), true);

                    if (!isset($dataArticle['isAccessibleForFree']) || $dataArticle['isAccessibleForFree']) {
                        if (!isset($dataArticle['datePublished'])) {
                            continue;
                        }
                        $publicationDate = $this->createDateFromString($dataArticle['datePublished']);
                        $hashPreview = $this->previewHelper->getImageUrlHashFromList($node, '//img', 'data-src');

                        if (!$hashPreview) {
                            $innerPageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                            if ($this->isNeedSkipArticle($innerPageContent)) {
                                continue;
                            }
                            $html = new Crawler();
                            $html->addHtmlContent($innerPageContent->getBody()->getContents(), 'UTF-8');

                            $hashPreview = $this->previewHelper->getOgImageUrlHash($html);
                        }

                        if ($publicationDate > $lastAddedPublicationTime) {
                            $result[] = new ArticleItem($pageLink, $title, $publicationDate, $hashPreview);
                        }
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