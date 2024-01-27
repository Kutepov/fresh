<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\it\Mondo;

use common\components\scrapers\common\ArticleBodyScraper;
use common\components\scrapers\common\helpers\PreviewHelper;
use common\components\scrapers\common\Config;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\SelectorsRemover;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\XPathParserV2;
use common\components\scrapers\dto\ArticleItem;
use GuzzleHttp\Promise\Coroutine;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Class Scraper
 * @package common\components\scrapers\sources\it\Mondo;
 *
 * @Config (
 * timezone="Europe/Paris", urls={
 * "https://www.ilsole24ore.com/sez/mondo",
 * "https://www.ilsole24ore.com/sez/economia",
 * "https://www.ilsole24ore.com/sez/finanza",
 * "https://www.ilsole24ore.com/sez/finanza-personale",
 * "https://www.ilsole24ore.com/sez/norme-e-tributi",
 * "https://www.ilsole24ore.com/sez/arteconomy",
 * "https://www.ilsole24ore.com/sez/casa",
 * "https://www.ilsole24ore.com/sez/commenti",
 * "https://www.ilsole24ore.com/sez/salute",
 * "https://www.ilsole24ore.com/sez/cultura",
 * "https://www.ilsole24ore.com/sez/scuola",
 * "https://www.ilsole24ore.com/sez/food",
 * "https://www.ilsole24ore.com/sez/sostenibilita",
 * "https://www.ilsole24ore.com/sez/how-to-spend-it",
 * "https://www.ilsole24ore.com/sez/sport24",
 * "https://www.ilsole24ore.com/sez/management",
 * "https://www.ilsole24ore.com/sez/tecnologia",
 * "https://www.ilsole24ore.com/sez/moda",
 * "https://www.ilsole24ore.com/sez/viaggi",
 * "https://www.ilsole24ore.com/sez/motori",
 * "https://www.ilsole24ore.com/sez/italia"
 * })
 */
class Scraper extends \common\components\scrapers\common\Scraper implements ArticleBodyScraper
{
    /**
     * @var XPathParserV2
     */
    private $XPathParser;

    /**
     * @var SelectorsRemover
     */
    private $selectorRemover;

    /**
     * @var PreviewHelper
     */
    private $previewHelper;


    public function __construct(
        XPathParserV2    $XPathParser,
        SelectorsRemover $selectorRemover,
        PreviewHelper $previewHelper,
        $config = []
    )
    {
        $this->XPathParser = $XPathParser;
        $this->selectorRemover = $selectorRemover;
        $this->previewHelper = $previewHelper;

        parent::__construct($config);
    }

    public function parseArticleBody(string $url): PromiseInterface
    {
        return $this->sendAsyncRequestWithProxy(new Request('GET', $url))->then(function (ResponseInterface $response) {
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');
            return $this->XPathParser->parseDescription($html, '//p[@class="atext"][1]]');
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//div[@class='main-content']";
            $articlesNode = $html->filterXPath($selector);

            $this->selectorRemover->remove('//section[contains(@class, "rel--24plus")]', $articlesNode);

            $articles = $articlesNode->filterXPath("//div[contains(@class, 'aprev aprev--fbtm')]");

            $lastAddedPublicationTime = $this->lastPublicationTime;
            $lastAddedPublicationTime = $lastAddedPublicationTime->setTimezone($this->timezone);
            $result = [];
            $baseUrl = 'https://www.ilsole24ore.com';
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {

                    if ($node->filterXPath('//img[@class="badge-plus"]')->count()) {
                        continue;
                    }

                    $linkNode = $node->filterXPath("//h3[contains(@class, 'aprev-title')]//a");

                    if (!filter_var($linkNode->attr('href'), FILTER_VALIDATE_URL)) {
                        $pageLink = $baseUrl . $linkNode->attr('href');
                    } else {
                        $pageLink = $linkNode->attr('href');
                    }

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
