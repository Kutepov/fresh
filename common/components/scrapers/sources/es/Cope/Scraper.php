<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\es\Cope;

use common\components\scrapers\common\ArticleBodyScraper;
use common\components\scrapers\common\helpers\PreviewHelper;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\XPathParserV2;
use common\components\scrapers\common\Config;
use common\components\scrapers\dto\ArticleItem;
use GuzzleHttp\Promise\Coroutine;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Class Scraper
 * @package common\components\scrapers\sources\Cope
 *
 * @Config (
 * timezone="Europe/Madrid", urls={
 * "https://www.cope.es/emisoras",
 * "https://www.cope.es/opinion",
 * "https://www.cope.es/actualidad",
 * "https://www.cope.es/deportes",
 * "https://www.cope.es/religion",
 * "https://www.cope.es/trecetv"
 * })
 */
class Scraper extends \common\components\scrapers\common\Scraper implements ArticleBodyScraper
{
    /**
     * @var XPathParserV2
     */
    private $XPathParser;

    /**
     * @var PreviewHelper
     */
    private $previewHelper;

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
            return $this->XPathParser->parseDescription($html, '//div[contains(@class, "subtitle")]//h2');
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//main";
            $articlesNode = $html->filterXPath($selector);

            $articles = $articlesNode->filterXPath("//article");
            $lastAddedPublicationTime = $this->lastPublicationTime->setTimezone($this->timezone);
            $baseUrl = 'https://www.cope.es';

            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {

                    if (!$node->filterXPath('//h2//a|//h3//a')->count()) {
                        continue;
                    }

                    $pageLink = $node->filterXPath('//h2//a|//h3//a')->attr('href');

                    if (!filter_var($pageLink, FILTER_VALIDATE_URL)) {
                        $pageLink = $baseUrl . $pageLink;
                    }

                    $title = $node->filterXPath('//h2//a|//h3//a')->text();
                    $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                    if ($this->isNeedSkipArticle($pageContent)) {
                        continue;
                    }

                    $html = new Crawler();
                    $html->addHtmlContent($pageContent->getBody()->getContents(), 'UTF-8');

                    $articlePubDate = $html->filterXPath("//head//meta[@property='article:published_time']")->first();
                    if (!$articlePubDate->count()) {
                        continue;
                    }
                    $pubDateAttr = $articlePubDate->attr('content');
                    $hashPreview = $this->previewHelper->getOgImageUrlHash($html, 'cope-redes-sociales');
                    $publicationDate = $this->createDateFromString($pubDateAttr);

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
