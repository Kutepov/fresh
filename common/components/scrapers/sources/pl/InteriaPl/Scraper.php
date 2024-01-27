<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\pl\InteriaPl;

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
 * @package common\components\scrapers\sources\pl\InteriaPl;
 *
 * @Config (
 * timezone="Europe/Warsaw", urls={
 * "https://biznes.interia.pl/",
 * "https://fakty.interia.pl/",
 * "https://gry.interia.pl/",
 * "https://kobieta.interia.pl/",
 * "https://menway.interia.pl/",
 * "https://mobtech.interia.pl/",
 * "https://motoryzacja.interia.pl/",
 * "https://muzyka.interia.pl/",
 * "https://nt.interia.pl/",
 * "https://swiatseriali.interia.pl/"
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
            return $this->XPathParser->parseDescription($html, '//p[@class="article-lead"][1]');
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//ul[contains(@class, 'brief-list-items')]";
            $articlesNode = $html->filterXPath($selector)->first();
            $this->selectorsRemover->remove(
                "//li[contains(@class, 'has-mixe')]",
                $articlesNode
            );
            $articles = $articlesNode->filterXPath("//li[contains(@class, 'brief-list-item')]");

            $lastAddedPublicationTime = $this->lastPublicationTime;
            $lastAddedPublicationTime = $lastAddedPublicationTime->setTimezone($this->timezone);
            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);

                if (
                    preg_match('/has-mixerAdTopRight|gora_srodek|mixer-player-tile/', $node->attr('class'))) {
                    continue;
                }

                try {
                    $pageLink = $node->filterXPath("//a[contains(@class, 'tile-magazine-thumb')]")->first()->attr('href');
                    $shcema = parse_url($pageLink, PHP_URL_SCHEME);
                    if (!$shcema) {
                        $pageLink = ltrim($pageLink, '/');
                        $pageLink = $url . $pageLink;
                    }

                    $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                    if ($this->isNeedSkipArticle($pageContent)) {
                        continue;
                    }
                    $html = new Crawler();
                    $html->addHtmlContent($pageContent->getBody()->getContents(), 'UTF-8');

                    $dateTimeNode = $html->filterXPath("//meta[@itemprop='datePublished']");
                    if ($dateTimeNode->count()) {
                        $pubDateAttr = $dateTimeNode->attr('content');
                    }
                    else {
                        continue;
                    }
                    $hashPreview = $this->previewHelper->getOgImageUrlHash($html);
                    $publicationDate = $this->createDateFromString($pubDateAttr);


                    if ($publicationDate > $lastAddedPublicationTime) {
                        $title = $html->filterXPath("//h1[contains(@class, 'article-title')]")->text();

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
