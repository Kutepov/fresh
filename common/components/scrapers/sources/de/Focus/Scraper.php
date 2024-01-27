<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\de\Focus;

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
 * @package common\components\scrapers\sources\de\Focus;
 *
 * @Config (
 * timezone="Europe/Berlin", urls={
 * "https://www.focus.de/politik/",
 * "https://www.focus.de/finanzen/",
 * "https://www.focus.de/perspektiven/",
 * "https://www.focus.de/wissen/",
 * "https://www.focus.de/gesundheit/",
 * "https://www.focus.de/kultur/",
 * "https://www.focus.de/panorama/",
 * "https://www.focus.de/sport/",
 * "https://www.focus.de/digital/",
 * "https://www.focus.de/reisen/",
 * "https://www.focus.de/videos/"
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
            return $this->XPathParser->parseDescription($html, '//div[@class="leadIn"]//p[1]');
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//main[@id='main']";
            $articlesNode = $html->filterXPath($selector);

            $articles = $articlesNode->filterXPath("//article");

            $lastAddedPublicationTime = $this->lastPublicationTime;
            $lastAddedPublicationTime = $lastAddedPublicationTime->setTimezone($this->timezone);
            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    $linkNode = $node->filterXPath("//a");

                    if (!$linkNode->count()) {
                        continue;
                    }

                    $pageLink = $linkNode->attr('href');

                    if (stripos($pageLink, 'focus.de') === false) {
                        continue;
                    }

                    $title = $node->filterXPath("//em//h3|//h4|//div[contains(@class, 'vidTeaserfulldouble')]//h3")->first()->text();

                    $dateNode = $node->filterXPath("//span[@class='date']|//span[@class='greydate']")->first();

                    if ($dateNode->count()) {
                        $pubDateAttr = $this->prepareDateString($dateNode->text());
                    } else {
                        $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                        if ($this->isNeedSkipArticle($pageContent)) {
                            continue;
                        }
                        $html = new Crawler();
                        $html->addHtmlContent($pageContent->getBody()->getContents(), 'UTF-8');

                        $dataArticle = $html->filterXPath(
                            "//script[@type='application/ld+json']")
                            ->first();

                        if (!$dataArticle->count()) {
                            continue;
                        }

                        $dataArticle = json_decode($dataArticle->text(), true);

                        if (!isset($dataArticle['datePublished'])) {
                            continue;
                        }

                        $pubDateAttr = $dataArticle['datePublished'];
                    }

                    $publicationDate = $this->createDateFromString($pubDateAttr);
                    $hashPreview = $this->previewHelper->getImageUrlHashFromList($node, "//img", "data-src");

                    if ($publicationDate >= $lastAddedPublicationTime) {
                        $result[] = new ArticleItem($pageLink, $title, $publicationDate, $hashPreview);
                    }
                } catch (\Throwable $exception) {
                    $this->logArticleItemException($exception, $pageLink);
                }

            }

            yield $result;
        });
    }

    private function prepareDateString($dateString)
    {
        [$dateStr, $time] = mb_split(' | ', $dateString);
        [, $date] = mb_split(', ', $dateStr);
        return $date . ' ' . $time;
    }
}
