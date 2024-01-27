<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\us\Staradvertiser;

use common\components\scrapers\common\ArticleBodyScraper;
use common\components\scrapers\common\helpers\PreviewHelper;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\XPathParserV2;
use common\components\scrapers\dto\ArticleItem;
use GuzzleHttp\Promise\Coroutine;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DomCrawler\Crawler;
use common\components\scrapers\common\Config;

/**
 * Class Scraper
 * @package common\components\scrapers\sources\us\Staradvertiser
 *
 * @Config (timezone="America/New_York", urls={
 * "https://www.staradvertiser.com/tag/coronavirus-outbreak-resources/",
 * "https://www.staradvertiser.com/category/breaking-news/",
 * "https://www.staradvertiser.com/tag/america-in-turmoil/",
 * "https://www.staradvertiser.com/category/hawaii-news/#next-18",
 * "https://www.staradvertiser.com/category/hawaii-news/kokua-line/",
 * "https://www.staradvertiser.com/tag/politics/",
 * "https://www.staradvertiser.com/category/sports/#next-18",
 * "https://www.staradvertiser.com/tag/business/#next-18",
 * "https://www.staradvertiser.com/category/letters/#next-eight",
 * "https://www.staradvertiser.com/category/features/#next-18",
 * "https://www.staradvertiser.com/tag/column/",
 * "https://www.staradvertiser.com/tag/odd-news/",
 * "https://www.staradvertiser.com/category/live-well/",
 * "https://www.staradvertiser.com/sports/"
 * })
 */
class Scraper extends \common\components\scrapers\common\Scraper implements ArticleBodyScraper
{

    private const MONTHS = [
        'Jan.' => 1,
        'Feb.' => 2,
        'March' => 3,
        'April' => 4,
        'May' => 5,
        'June' => 6,
        'July' => 7,
        'Aug.' => 8,
        'Sept.' => 9,
        'Oct.' => 10,
        'Nov.' => 11,
        'Dec.' => 12
    ];


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
            return $this->XPathParser->parseDescription($html, '//div[@id="article-content"]//p[2]');
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler($pageContent->getBody()->getContents());

            $selector = "//div[@class='container-fluid narrow mb-4 pb-4']|//div[@class='container-fluid mb-4 pb-4']";
            $articlesNode = $html->filterXPath($selector)->first();

            $articles = $articlesNode->filterXPath("//article[contains(@id, 'post-')]");

            $lastAddedPublicationTime = $this->lastPublicationTime;

            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    if ($node->filterXPath("//a[@class='premium']")->count()) {
                        continue;
                    }

                    $linkNode = $node->filterXPath('//h6//a')->first();
                    if ($linkNode->count() === 0) {
                        continue;
                    }
                    $pageLink = $linkNode->attr('href');

                    $title = $linkNode->text();

                    $postDate = $node->filterXPath("//li[contains(@class, 'postdate')]")->first();

                    $hashPreview = $this->previewHelper->getImageUrlHashFromList($node, '//img', 'data-src');

                    if (!$hashPreview || !$postDate->count()) {
                        $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                        if ($this->isNeedSkipArticle($pageContent)) {
                            continue;
                        }
                        $html = new Crawler();
                        $html->addHtmlContent($pageContent->getBody()->getContents(), 'UTF-8');

                        $hashPreview = $this->previewHelper->getOgImageUrlHash($html);
                        $articlePubDate = $html->filterXPath("//head//meta[@property='article:published_time']")->first();
                        $pubDateAttr = $articlePubDate->attr('content');
                        if (!$pubDateAttr) {
                            continue;
                        }

                        $publicationDate = $this->createDateFromString($pubDateAttr);
                    } else {
                        $publicationDate = $this->createDateFromString($this->prepareDateString($postDate->text()));
                    }

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

    private function prepareDateString(string $string): string
    {
        if ($string === 'Today') {
            return date('d-m-Y');
        }
        [$date, $year] = mb_split(', ', $string);
        [$month, $day] = mb_split(' ', $date);
        $month = self::MONTHS[$month];
        return $day . '-' . $month . '-' . $year;
    }

}