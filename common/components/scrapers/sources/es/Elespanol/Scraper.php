<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\es\Elespanol;

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
 * @package common\components\scrapers\sources\Elespanol
 *
 * @Config (
 * timezone="Europe/Madrid", urls={
 * "https://www.elespanol.com/espana/",
 * "https://www.elespanol.com/opinion/",
 * "https://www.elespanol.com/reportajes/",
 * "https://www.elespanol.com/mundo/",
 * "https://www.elespanol.com/invertia/",
 * "https://www.elespanol.com/ciencia/",
 * "https://www.elespanol.com/el-cultural/",
 * "https://www.elespanol.com/deportes/",
 * "https://www.elespanol.com/porfolio/",
 * "https://www.elespanol.com/corazon/",
 * "https://www.elespanol.com/enclave-ods/",
 * "https://www.elespanol.com/mujer/"
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
            return $this->XPathParser->parseDescription($html, '//div[contains(@class, "c-article-content")]//p[1]');
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//div[@id='content']";
            $articlesNode = $html->filterXPath($selector);

            $articles = $articlesNode->filterXPath("//article|//section[@class='mod-obsesiones']");
            $lastAddedPublicationTime = $this->lastPublicationTime->setTimezone($this->timezone);
            $baseUrl = 'https://www.elespanol.com';

            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {

                    if (stripos($node->attr('class'), 'art--closed') !== false || $node->attr('class') === 'art') {
                        continue;
                    }

                    $pageLink = $node->filterXPath('//header//a|//a[@class="art__link"]|//h3//a')->attr('href');

                    if (!filter_var($pageLink, FILTER_VALIDATE_URL)) {
                        $pageLink = $baseUrl . $pageLink;
                    }

                    $title = $node->filterXPath('//header//a|//header//h3|//h3//a')->text();
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
