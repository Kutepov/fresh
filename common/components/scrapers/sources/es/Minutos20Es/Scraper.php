<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\es\Minutos20Es;

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
 * @package common\components\scrapers\sources\es\Minutos20Es;
 *
 * @Config (
 * timezone="Europe/Madrid", urls={
 * "https://www.20minutos.es/andalucia/",
 * "https://www.20minutos.es/cataluna/",
 * "https://www.20minutos.es/ciencia/",
 * "https://www.20minutos.es/ciudades/",
 * "https://www.20minutos.es/comunidad-valenciana/",
 * "https://www.20minutos.es/cultura/",
 * "https://www.20minutos.es/deportes/",
 * "https://www.20minutos.es/economia/",
 * "https://www.20minutos.es/empleo/",
 * "https://www.20minutos.es/gastronomia/",
 * "https://www.20minutos.es/gente/",
 * "https://www.20minutos.es/gonzoo/",
 * "https://www.20minutos.es/internacional/",
 * "https://www.20minutos.es/madrid/",
 * "https://www.20minutos.es/medio-ambiente/",
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
            return $this->XPathParser->parseDescription($html, '//div[@class="article-text"]//p[1]');
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//section[contains(@class, 'board board-')]";
            $articlesNode = $html->filterXPath($selector);

            $articles = $articlesNode->filterXPath("//ul//li|//article[contains(@class, 'media media-big')]");

            $lastAddedPublicationTime = $this->lastPublicationTime;
            $lastAddedPublicationTime = $lastAddedPublicationTime->setTimezone($this->timezone);

            $baseLink = 'https://www.lavanguardia.com/';
            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    $articleNode = $node->filterXPath("//header//h1//a")->first();
                    if (!$articleNode->count()) {
                        continue;
                    }
                    $pageLink = $articleNode->attr('href');
                    $title = $articleNode->text();
                    $schame = parse_url($pageLink, PHP_URL_SCHEME);
                    if (!$schame) {
                        $pageLink = ltrim($pageLink, '/');
                        $pageLink = $baseLink.$pageLink;
                    }

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

                    $hashPreview = $this->previewHelper->getOgImageUrlHash($html, 'images/20m_es_default');

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
