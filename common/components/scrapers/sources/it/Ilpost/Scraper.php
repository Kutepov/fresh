<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\it\Ilpost;

use common\components\scrapers\common\ArticleBodyScraper;
use common\components\scrapers\common\helpers\PreviewHelper;
use common\components\scrapers\common\Config;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\XPathParserV2;
use common\components\scrapers\dto\ArticleItem;
use GuzzleHttp\Promise\Coroutine;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Class Scraper
 * @package common\components\scrapers\sources\it\Ilpost;
 *
 * @Config (
 * timezone="Europe/Paris", urls={
 * "https://www.ilpost.it/italia/",
 * "https://www.ilpost.it/mondo/",
 * "https://www.ilpost.it/politica/",
 * "https://www.ilpost.it/tecnologia/",
 * "https://www.ilpost.it/internet/",
 * "https://www.ilpost.it/scienza/",
 * "https://www.ilpost.it/cultura/",
 * "https://www.ilpost.it/economia/",
 * "https://www.ilpost.it/europa/",
 * "https://www.ilpost.it/sport/",
 * "https://www.ilpost.it/media/",
 * "https://www.ilpost.it/moda/"
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
        XPathParserV2    $XPathParser,
        PreviewHelper $previewHelper,
        $config = []
    )
    {
        $this->XPathParser = $XPathParser;
        $this->previewHelper = $previewHelper;

        parent::__construct($config);
    }

    public function parseArticleBody(string $url): PromiseInterface
    {
        return $this->sendAsyncRequestWithProxy(new Request('GET', $url))->then(function (ResponseInterface $response) {
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');
            return $this->XPathParser->parseDescription($html, '//div[@id="singleBody"]//p[1]');
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//section[@id='content']";
            $articlesNode = $html->filterXPath($selector);

            $articles = $articlesNode->filterXPath("//article");

            $lastAddedPublicationTime = $this->lastPublicationTime;
            $lastAddedPublicationTime = $lastAddedPublicationTime->setTimezone($this->timezone);
            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {

                    $linkNode = $node->filterXPath("//h2//a");
                    if (!$linkNode->count()) {
                        continue;
                    }

                    $pageLink = $linkNode->attr('href');
                    $title = $linkNode->text();

                    $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                    if ($this->isNeedSkipArticle($pageContent)) {
                        continue;
                    }
                    $html = new Crawler();
                    $html->addHtmlContent($pageContent->getBody()->getContents(), 'UTF-8');

                    $articlePubDate = $html->filterXPath("//meta[@property='article:published_time']")->first();
                    if (!$articlePubDate->count()) {
                        continue;
                    }
                    $pubDateAttr = $articlePubDate->attr('content');

                    $publicationDate = $this->createDateFromString($pubDateAttr);
                    $hashPreview = $this->previewHelper->getOgImageUrlHash($html);

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
