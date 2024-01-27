<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\pl\Wyborcza;

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
 * @package common\components\scrapers\sources\pl\Wyborcza
 *
 * @Config (
 * timezone="Europe/Warsaw", urls={
 * "https://wyborcza.pl/0,75398.html#TRNavSST",
 * "https://wyborcza.pl/0,173236.html#TRNavSST",
 * "https://wyborcza.pl/0,75399.html#TRNavSST",
 * "https://wyborcza.pl/0,75968.html#TRNavSST",
 * "https://wyborcza.pl/0,155287.html#TRNavSST",
 * "https://wyborcza.pl/0,75400.html#TRNavSST",
 * "https://wyborcza.pl/0,156282.html#TRNavSST",
 * "https://wyborcza.pl/0,75410.html#TRNavSST",
 * "https://wyborcza.pl/0,154903.html#TRNavSST",
 * "https://wyborcza.pl/TylkoZdrowie/0,0.html#TRNavSST",
 * "https://wyborcza.pl/0,171791.html#TRNavSST"
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
        return $this->sendAsyncRequestWithProxy(new Request('GET', $url . '?disableRedirects=true&squid_js=false'))->then(function (ResponseInterface $response) {
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'ISO-8859-2');
            return $this->XPathParser->parseDescription($html, '//section[@class="article-lead"][1]');
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'ISO-8859-2');

            $selector = "//div[contains(@class, 'body')]//ul";
            $articlesNode = $html->filterXPath($selector);
            $articles = $articlesNode->filterXPath("//li[contains(@class, 'entry')]");
            $lastAddedPublicationTime = $this->lastPublicationTime;

            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    $pageLink = $node->filterXPath('//h2//a|//h3//a')->first()->attr('href');
                    $pageLink = preg_replace('#^http://#', 'https://', $pageLink);
                    $titleNode = $node->filterXPath('//h2//a|//h3//a')->first();
                    if (!$titleNode->count()) {
                        continue;
                    }
                    $title = $titleNode->text();
                    $dateNode = $node->filterXPath("//span[@class='when']")->first();
                    if (!$dateNode->count()) {
                        continue;
                    }

                    $pubDateAttr = $dateNode->text();

                    $innerPageContent = yield $this->sendAsyncRequestWithProxy(new Request(
                            'GET',
                            $pageLink . '?disableRedirects=true&squid_js=false')
                    );
                    if ($this->isNeedSkipArticle($innerPageContent)) {
                        continue;
                    }

                    $html = new Crawler($innerPageContent->getBody()->getContents());

                    $json = $html->filterXPath(
                        "//script[@type='application/ld+json']"
                    )->first();
                    if (!$json->count()) {
                        continue;
                    }

                    $isFree = $this->isFree($json->text());

                    if (!$isFree) {
                        continue;
                    }


                    $publicationDate = $this->createDateFromString($pubDateAttr);
                    $imgHash = $this->previewHelper->getImageUrlHashFromList($node);

                    if ($publicationDate > $lastAddedPublicationTime) {
                        $result[] = new ArticleItem($pageLink, $title, $publicationDate, $imgHash);
                    }
                } catch (\Throwable $exception) {
                    $this->logArticleItemException($exception, $pageLink);
                }
            }

            yield $result;
        });
    }

    private function isFree(string $string): bool
    {
        $json = json_decode($string);
        return $json->isAccessibleForFree === 'True';
    }
}
