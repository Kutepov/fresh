<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\it\Corriere;

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
 * @package common\components\scrapers\sources\it\Corriere;
 *
 * @Config (
 * timezone="Europe/Paris", urls={
 * "https://www.corriere.it/politica/",
 * "https://www.corriere.it/cronache/",
 * "https://www.corriere.it/esteri/",
 * "https://www.corriere.it/sport/",
 * "https://www.corriere.it/cultura/",
 * "https://www.corriere.it/la-lettura/",
 * "https://www.corriere.it/salute/",
 * "https://www.corriere.it/scienze-ambiente/",
 * "https://www.corriere.it/animali/",
 * "https://www.corriere.it/tecnologia/",
 * "https://motori.corriere.it/"
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
        XPathParserV2 $XPathParser,
        PreviewHelper $previewHelper,
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
            $html->addHtmlContent($response->getBody()->getContents(), 'iso-8859-1');
            return $this->XPathParser->parseDescription($html, '//section[@class="post-content"]//p', false);
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'iso-8859-1');

            $selector = "//div[@class='container']|//section[@class='mm_hs_s00 third-column']";
            $articlesNode = $html->filterXPath($selector);

            $articles = $articlesNode->filterXPath("//div[contains(@class, 'media-news__content')]|//article");

            $lastAddedPublicationTime = $this->lastPublicationTime;
            $lastAddedPublicationTime = $lastAddedPublicationTime->setTimezone($this->timezone);
            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    $linkNode = $node->filterXPath("//a");
                    $pageLink = $linkNode->attr('href');

                    if (stripos($pageLink, '/cultura/') !== false) {
                        $pageLink = 'https:'.$pageLink;
                    }

                    if (!filter_var($pageLink, FILTER_VALIDATE_URL)) {
                        continue;
                    }

                    $titleNode = $node->filterXPath("//h4|//h2|//h3");
                    if (!$titleNode->count()) {
                        continue;
                    }
                    $title = $titleNode->text();

                    $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                    if ($this->isNeedSkipArticle($pageContent)) {
                        continue;
                    }
                    $html = new Crawler();
                    $html->addHtmlContent($pageContent->getBody()->getContents(), 'iso-8859-1');

                    $articlePubDate = $html->filterXPath("//meta[@property='article:published_time']")->first();
                    if (!$articlePubDate->count()) {
                        continue;
                    }
                    $pubDateAttr = $articlePubDate->attr('content');

                    if ($pubDateAttr === 'TZ') {
                        continue;
                    }

                    $ldJson = $html->filterXPath("//script[@type='application/ld+json']")->first();
                    if ($ldJson->count()) {
                        $ldJson = json_decode($ldJson->text(), true);

                        if ($ldJson['isAccessibleForFree'] === 'False') {
                            continue;
                        }
                    } else {
                        if ($html->filterXPath('//div[@class="paywall-content"]')->count()) {
                            continue;
                        }
                    }

                    $publicationDate = $this->createDateFromString($pubDateAttr);
                    $hashPreview = $this->previewHelper->getImageUrlHashFromList($node, '//img', 'data-src');

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
