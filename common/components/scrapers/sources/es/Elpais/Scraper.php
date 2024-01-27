<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\es\Elpais;

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
 * @package common\components\scrapers\sources\Elpais
 *
 * @Config (
 * timezone="Europe/Madrid", urls={
 * "https://elpais.com/internacional/",
 * "https://elpais.com/opinion/",
 * "https://elpais.com/espana/",
 * "https://elpais.com/economia/",
 * "https://elpais.com/sociedad/",
 * "https://elpais.com/educacion/",
 * "https://elpais.com/clima-y-medio-ambiente/",
 * "https://elpais.com/ciencia/",
 * "https://elpais.com/salud-y-bienestar/",
 * "https://elpais.com/tecnologia/",
 * "https://elpais.com/cultura/",
 * "https://elpais.com/babelia/",
 * "https://elpais.com/deportes/",
 * "https://elpais.com/television/",
 * "https://elpais.com/gente/",
 * "https://elpais.com/eps/"
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
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');
            return $this->XPathParser->parseDescription($html, '//article//h2[@class="a_st"]');
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

            $baseLink = 'https://elpais.com';
            $articles = $articlesNode->filterXPath("//article");

            $lastAddedPublicationTime = $this->lastPublicationTime->setTimezone($this->timezone);

            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    if ($node->filterXPath('//span[@name="elpais_ico"]')->count()) {
                        continue;
                    }

                    $pageLink = $node->filterXPath('//h2//a')->attr('href');

                    if (filter_var($pageLink, FILTER_VALIDATE_URL)) {
                        continue;
                    }

                    $pageLink = $baseLink . $pageLink;

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
