<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\es\Larazon;

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
 * @package common\components\scrapers\sources\Larazon
 *
 * @Config (
 * timezone="Europe/Madrid", urls={
 * "https://www.larazon.es/espana/",
 * "https://www.larazon.es/opinion/",
 * "https://www.larazon.es/internacional/",
 * "https://www.larazon.es/economia/",
 * "https://www.larazon.es/cultura/",
 * "https://www.larazon.es/sociedad/",
 * "https://www.larazon.es/salud/",
 * "https://www.larazon.es/deportes/",
 * "https://www.larazon.es/gente/",
 * "https://www.larazon.es/lifestyle/",
 * "https://www.larazon.es/ciencia/",
 * "https://www.larazon.es/tecnologia/",
 * "https://www.larazon.es/television/",
 * "https://www.larazon.es/motor/",
 * "https://www.larazon.es/viajes/",
 * "https://www.larazon.es/gastronomia/",
 * "https://www.larazon.es/compras/",
 * "https://www.larazon.es/guias/"
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
            return $this->XPathParser->parseDescription($html, '//article[@class="article-body"]//h2');
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//main[@role='main']";
            $articlesNode = $html->filterXPath($selector);

            $articles = $articlesNode->filterXPath("//article[contains(@class, 'card ')]");
            $lastAddedPublicationTime = $this->lastPublicationTime->setTimezone($this->timezone);

            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    $pageLink = $node->filterXPath('//h2//a')->attr('href');

                    $title = $node->filterXPath('//h2//a')->text();
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
                    $hashPreview = $this->previewHelper->getOgImageUrlHash($html);
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
