<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\au\News;

use common\components\guzzle\Guzzle;
use common\components\scrapers\common\ArticleBodyScraper;
use common\components\scrapers\common\helpers\PreviewHelper;
use common\components\scrapers\common\Config;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\XPathParserV2;
use common\components\scrapers\common\symfony\SiteParserModule\Infrastructure\SelectorsRemover;
use common\components\scrapers\dto\ArticleItem;
use GuzzleHttp\Promise\Coroutine;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Class Scraper
 * @package common\components\scrapers\sources\au\News;
 *
 * @Config (
 * timezone="Australia/Sydney", urls={
 * "https://www.news.com.au/national",
 * "https://www.news.com.au/world",
 * "https://www.news.com.au/lifestyle",
 * "https://www.news.com.au/travel",
 * "https://www.news.com.au/entertainment",
 * "https://www.news.com.au/finance",
 * "https://www.news.com.au/sport"
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

    /**
     * @var SelectorsRemover
     */
    private $selectorRemover;

    public function __construct(
        PreviewHelper $previewHelper,
        XPathParserV2 $XPathParser,
        SelectorsRemover $selectorRemover,
                      $config = []
    )
    {
        $this->previewHelper = $previewHelper;
        $this->XPathParser = $XPathParser;
        $this->selectorRemover = $selectorRemover;

        parent::__construct($config);
    }

    public function parseArticleBody(string $url): PromiseInterface
    {
        return $this->sendAsyncRequestWithProxy(new Request('GET', $url))->then(function (ResponseInterface $response) {
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents());
            return $this->XPathParser->parseDescription($html, '//p[@class="story-intro"]');
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//section[@class='site-content']";
            $articlesNode = $html->filterXPath($selector);

            $this->selectorRemover->remove("//div[@id='content-1']", $articlesNode);

            $articles = $articlesNode->filterXPath("//article[@class='storyblock']");
            $lastAddedPublicationTime = $this->lastPublicationTime;
            $lastAddedPublicationTime = $lastAddedPublicationTime->setTimezone($this->timezone);
            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {

                    $linkNode = $node->filterXPath("//h4//a|//div[@class='link-wrapper']//a");
                    $pageLink = $linkNode->attr('href');

                    $title = $node->filterXPath("//h4|//div[@class='link-wrapper']")->first()->text();

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
                        $hashPreview = $this->previewHelper->getImageUrlHashFromList($node, '//img', ['data-src', 'src']);

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
