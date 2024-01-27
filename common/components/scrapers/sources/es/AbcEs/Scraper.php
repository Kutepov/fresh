<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\es\AbcEs;

use common\components\scrapers\common\ArticleBodyScraper;
use common\components\scrapers\common\helpers\PreviewHelper;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\XPathParserV2;
use common\components\scrapers\common\symfony\SiteParserModule\Infrastructure\SelectorsRemover;
use common\components\scrapers\common\Config;
use common\components\scrapers\dto\ArticleItem;
use GuzzleHttp\Promise\Coroutine;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Class Scraper
 * @package common\components\scrapers\sources\AbcEs
 *
 * @Config (
 * timezone="Europe/Madrid", urls={
 * "https://www.abc.es/ciencia/",
 * "https://www.abc.es/cultura/",
 * "https://www.abc.es/deportes/",
 * "https://www.abc.es/economia/",
 * "https://www.abc.es/espana/",
 * "https://www.abc.es/espana/madrid/",
 * "https://www.abc.es/familia/",
 * "https://www.abc.es/historia/",
 * "https://www.abc.es/internacional/",
 * "https://www.abc.es/motor/",
 * "https://www.abc.es/multimedia/videos/",
 * "https://www.abc.es/natural/",
 * "https://www.abc.es/play/",
 * "https://www.abc.es/recreo/",
 * "https://www.abc.es/salud/",
 * "https://www.abc.es/sociedad/",
 * "https://www.abc.es/tecnologia/",
 * "https://www.abc.es/viajar/"
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
    private $selectorsRemover;

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
        $this->previewHelper = $previewHelper;
        $this->selectorsRemover = $selectorsRemover;
        $this->XPathParser = $XPathParser;

        parent::__construct($config);
    }

    public function parseArticleBody(string $url): PromiseInterface
    {
        return $this->sendAsyncRequestWithProxy(new Request('GET', $url))->then(function (ResponseInterface $response) {
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');
            return $this->XPathParser->parseDescription($html, '//span[@class="encabezado-articulo"]//h2');
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//section[contains(concat(' ', @class, ' '), ' caja-portada ')]";
            $articlesNode = $html->filterXPath($selector);

            $this->selectorsRemover->remove(
                "//span[contains(@class, 'publicidad')]|
            //span[contains(@class, 'start-row')][ul[contains(@class, 'paginacion-portada')]]|
            //span[contains(@class, 'patrocinados')]|
            ",
                $articlesNode
            );

            $baseLink = 'https://www.abc.es/';
            $articles = $articlesNode->filterXPath("//span[contains(@class, 'start-row')]");
            $latestArticle = null;
            $lastAddedPublicationTime = $this->lastPublicationTime->setTimezone($this->timezone);

            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {

                    $articleNode = $node->filterXPath("//article[contains(@class, 'articulo-portada')]//a");
                    if (!$articleNode->count()) {
                        continue;
                    }
                    $pageLink = $articleNode->attr('href');
                    $schame = parse_url($pageLink, PHP_URL_SCHEME);
                    if (!$schame) {
                        $pageLink = ltrim($pageLink, '/');
                        $pageLink = $baseLink.$pageLink;
                    }
                    $title = $articleNode->attr('title');
                    $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                    if ($this->isNeedSkipArticle($pageContent)) {
                        continue;
                    }
                    $html = new Crawler();
                    $html->addHtmlContent($pageContent->getBody()->getContents(), 'UTF-8');

                    $articlePubDate = $html->filterXPath("//head//meta[@name='date']")->first();
                    if (!$articlePubDate->count()) {
                        continue;
                    }
                    $pubDateAttr = $articlePubDate->attr('content');
                    $hashPreview = $this->previewHelper->getOgImageUrlHash($html);

                    if (!$hashPreview) {
                        $innerPageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                        if ($this->isNeedSkipArticle($innerPageContent)) {
                            continue;
                        }
                        $html = new Crawler();
                        $html->addHtmlContent($innerPageContent->getBody()->getContents(), 'UTF-8');

                        $hashPreview = $this->previewHelper->getOgImageUrlHash($html);
                    }

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
