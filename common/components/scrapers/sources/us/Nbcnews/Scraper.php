<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\us\Nbcnews;

use common\components\scrapers\common\ArticleBodyScraper;
use common\components\scrapers\common\helpers\PreviewHelper;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\XPathParserV2;
use common\components\scrapers\common\symfony\SiteParserModule\Infrastructure\SelectorsRemover;
use common\components\scrapers\dto\ArticleItem;
use GuzzleHttp\Promise\Coroutine;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DomCrawler\Crawler;
use common\components\scrapers\common\Config;

/**
 * Class Scraper
 * @package common\components\scrapers\sources\us\Nbcnews
 *
 * @Config (timezone="America/New_York", urls={
 * "https://www.nbcnews.com/investigations",
 * "https://www.nbcnews.com/health",
 * "https://www.nbcnews.com/tech-media",
 * "https://www.nbcnews.com/business",
 * "https://www.nbcnews.com/world",
 * "https://www.nbcnews.com/us-news",
 * "https://www.nbcnews.com/politics"
 * })
 */
class Scraper extends \common\components\scrapers\common\Scraper implements ArticleBodyScraper
{
    /**
     * @var PreviewHelper
     */
    private $previewHelper;

    /**
     * @var SelectorsRemover
     */
    private $selectorRemover;

    /**
     * @var XPathParserV2
     */
    private $XPathParser;

    public function __construct(
        PreviewHelper $previewHelper,
        SelectorsRemover $selectorsRemover,
        XPathParserV2 $XPathParser,
        $config = []
    )
    {
        $this->selectorRemover = $selectorsRemover;
        $this->previewHelper = $previewHelper;
        $this->XPathParser = $XPathParser;
        parent::__construct($config);
    }

    public function parseArticleBody(string $url): PromiseInterface
    {
        return $this->sendAsyncRequestWithProxy(new Request('GET', $url))->then(function (ResponseInterface $response) {
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents());
            return $this->XPathParser->parseDescription($html, '//header//div//div');
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler($pageContent->getBody()->getContents());

            $selector = "//body";
            $articlesNode = $html->filterXPath($selector)->first();

            $articles = $articlesNode->filterXPath("//article|//div[@class='pancake__tease pk-fy pk-fn-m']|//div[contains(@class, 'pancake-item-')]|//div[@class='wide-tease-item__wrapper df flex-column flex-row-m flex-nowrap-m']");

            $lastAddedPublicationTime = $this->lastPublicationTime;

            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                $this->selectorRemover->remove("//div[@class='wide-tease-item__unibrow-wrapper df']", $node);
                try {
                    $linkNode = $node->filterXPath('//h2//a|//h3//a')->first();
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

                    $dataArticle = $html->filterXPath("//time[@itemprop='datePublished']")->first();

                    if (!$dataArticle->count()) {

                        $dataArticle = $html->filterXPath("//script[@type='application/ld+json']")->eq(2)->first();

                        if (!$dataArticle->count()) {
                            continue;
                        }

                        $dataJson = json_decode($dataArticle->text(), true);

                        $pubDateAttr = $dataJson['datePublished'];
                    }
                    else {
                        $pubDateAttr = $dataArticle->attr('content');
                    }

                    $publicationDate = $this->createDateFromString($pubDateAttr);
                    $hashPreview = $this->previewHelper->getImageUrlHashFromList($node);

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