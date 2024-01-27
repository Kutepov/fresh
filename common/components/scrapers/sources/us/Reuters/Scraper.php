<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\us\Reuters;

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
 * @package common\components\scrapers\sources\us\Reuters
 *
 * @Config (timezone="America/New_York", urls={
 * "https://www.reuters.com/world/",
 * "https://www.reuters.com/business/",
 * "https://www.reuters.com/legal/",
 * "https://www.reuters.com/markets/",
 * "https://www.reuters.com/breakingviews/",
 * "https://www.reuters.com/technology/"
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
            return $this->XPathParser->parseDescription($html, '//div[contains(@class, "article-body__content")]//p[1]');
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler($pageContent->getBody()->getContents());

            $selector = "//main[@id='main-content']";
            $articlesNode = $html->filterXPath($selector)->first();

            $articles = $articlesNode->filterXPath("//div[contains(@class, 'media-story-card__hero')]|//div[contains(@class, 'media-story-card__hub')]");

            $lastAddedPublicationTime = $this->lastPublicationTime;
            $result = [];
            $baseUrlr = 'https://www.reuters.com';
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    $urlNode = $node->filterXPath("//a[@data-testid='Heading']")->first();
                    if (!$urlNode->count()) {
                        continue;
                    }
                    $pageLink = $baseUrlr . $urlNode->attr('href');

                    $title = $urlNode->text();

                    if (!$title) {
                        continue;
                    }

                    $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                    if ($this->isNeedSkipArticle($pageContent)) {
                        continue;
                    }

                    $html = new Crawler($pageContent->getBody()->getContents());

                    $hashPreview = $this->previewHelper->getOgImageUrlHash($html);
                    $dateNode = $html->filterXPath('//meta[@name="article:published_time"]');

                    if (!$dateNode->count()) {
                        continue;
                    }

                    $date = $dateNode->attr('content');

                    $publicationDate = $this->createDateFromString($date);

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