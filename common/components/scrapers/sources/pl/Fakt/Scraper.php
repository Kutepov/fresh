<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\pl\Fakt;

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
 * @package common\components\scrapers\sources\pl\Fakt
 *
 * @Config (
 * timezone="Europe/Warsaw", urls={
 * "https://www.fakt.pl/wydarzenia/polska",
 * "https://www.fakt.pl/wydarzenia/swiat",
 * "https://www.fakt.pl/wydarzenia/polska/bialystok",
 * "https://www.fakt.pl/wydarzenia/polska/krakow",
 * "https://www.fakt.pl/wydarzenia/polska/lublin",
 * "https://www.fakt.pl/wydarzenia/polska/lodz",
 * "https://www.fakt.pl/wydarzenia/polska/poznan",
 * "https://www.fakt.pl/wydarzenia/polska/rzeszow",
 * "https://www.fakt.pl/wydarzenia/polska/trojmiasto",
 * "https://www.fakt.pl/wydarzenia/polska/slask",
 * "https://www.fakt.pl/wydarzenia/polska/szczecin",
 * "https://www.fakt.pl/wydarzenia/polska/wroclaw",
 * "https://www.fakt.pl/wydarzenia/polska/warszawa",
 * "https://www.fakt.pl/wydarzenia/polityka",
 * "https://www.fakt.pl/kobieta/plotki",
 * "https://www.fakt.pl/pieniadze/finanse",
 * "https://www.fakt.pl/pieniadze/nieruchomosci",
 * "https://www.fakt.pl/pieniadze/prawo",
 * "https://www.fakt.pl/pieniadze/zakupy",
 * "https://www.fakt.pl/sport",
 * "https://www.fakt.pl/pieniadze/biznes"
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
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');
            return $this->XPathParser->parseDescription($html, '//p[@class="article-p"][1]');
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();

            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//div[contains(@class, ' grid-container ')]";
            $articlesNode = $html->filterXPath($selector);

            $articles = $articlesNode->filterXPath("//div[@class='list-item']");

            $lastAddedPublicationTime = $this->lastPublicationTime;

            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    if ($node->filterXPath('//div[@class="paywall-icon"]')->first()->count()) {
                        continue;
                    }

                    $pageLink = $node->filterXPath("//a")->first()->attr('href');
                    $title = $node->filterXPath("//h2")->first()->text();

                    $innerPageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                    if ($this->isNeedSkipArticle($innerPageContent)) {
                        continue;
                    }
                    $html = new Crawler();
                    $html->addHtmlContent($innerPageContent->getBody()->getContents(), 'UTF-8');

                    $articlePubDate = $html->filterXPath("//head//meta[@property='article:published_time']")->first();
                    $pubDateAttr = $articlePubDate->attr('content');
                    if (!$pubDateAttr) {
                        continue;
                    }
                    $publicationDate = $this->createDateFromString($pubDateAttr);

                    $hashPreview = $this->previewHelper->getImageUrlHashFromList($node);

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
