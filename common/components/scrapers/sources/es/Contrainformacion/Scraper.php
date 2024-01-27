<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\es\Contrainformacion;

use common\components\scrapers\common\ArticleBodyScraper;
use common\components\scrapers\common\services\HashImageService;
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
 * @package common\components\scrapers\sources\Contrainformacion
 *
 * @Config (
 * timezone="Europe/Madrid", urls={
 * "https://contrainformacion.es/category/nacional/",
 * "https://contrainformacion.es/category/divulgacion/",
 * "https://contrainformacion.es/category/economia/",
 * "https://contrainformacion.es/category/internacional-2/",
 * "https://contrainformacion.es/category/derechos-2/"
 * })
 */
class Scraper extends \common\components\scrapers\common\Scraper implements ArticleBodyScraper
{
    /**
     * @var XPathParserV2
     */
    private $XPathParser;

    /**
     * @var HashImageService
     */
    private $hashImageService;

    public function __construct(
        XPathParserV2 $XPathParser,
        HashImageService $hashImageService,
        $config = []
    )
    {
        $this->XPathParser = $XPathParser;
        $this->hashImageService = $hashImageService;

        parent::__construct($config);
    }

    public function parseArticleBody(string $url): PromiseInterface
    {
        return $this->sendAsyncRequestWithProxy(new Request('GET', $url))->then(function (ResponseInterface $response) {
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');
            return $this->XPathParser->parseDescription($html, '//div[@class="td-post-content"]//p[1]');
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//div[@id='td-outer-wrap']";
            $articlesNode = $html->filterXPath($selector);

            $articles = $articlesNode->filterXPath("//div[contains(@class, 'td_module_wrap td-animation-stack')]|//div[contains(@class, 'td-animation-stack td-big-grid-post-')]");

            $lastAddedPublicationTime = $this->lastPublicationTime->setTimezone($this->timezone);

            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {

                    $pageLink = $node->filterXPath('//h3//a')->attr('href');

                    $title = $node->filterXPath('//h3//a')->text();
                    $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                    if ($this->isNeedSkipArticle($pageContent)) {
                        continue;
                    }

                    $pubDateAttr = $node->filterXPath('//time')->attr('datetime');

                    $hashPreview = $this->hashImageService->hashImage($node->filterXPath('//img')->attr('src'));
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
