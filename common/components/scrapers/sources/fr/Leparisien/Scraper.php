<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\fr\Leparisien;

use common\components\guzzle\Guzzle;
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
 * @package common\components\scrapers\sources\fr\Leparisien;
 *
 * @Config (
 * timezone="Europe/Paris", urls={
 * "https://www.leparisien.fr/politique/",
 * "https://www.leparisien.fr/enquete/",
 * "https://www.leparisien.fr/international/",
 * "https://www.leparisien.fr/sports/",
 * "https://www.leparisien.fr/societe/sante/",
 * "https://www.leparisien.fr/sciences/",
 * "https://www.leparisien.fr/economie/",
 * "https://www.leparisien.fr/culture-loisirs/",
 * "https://www.leparisien.fr/high-tech/",
 * "https://www.leparisien.fr/environnement/",
 * "https://www.leparisien.fr/info-paris-ile-de-france-oise/"
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
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');
            return $this->XPathParser->parseDescription($html, '//header[@class="article_header"]//h2');
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//div[@class='pageContent container']";
            $articlesNode = $html->filterXPath($selector);

            $articles = $articlesNode->filterXPath("//div[contains(@class, 'story-preview story')]");
            $lastAddedPublicationTime = $this->lastPublicationTime;
            $lastAddedPublicationTime = $lastAddedPublicationTime->setTimezone($this->timezone);
            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    if ($node->filterXPath("//span[@class='tag label abo']")->first()->count() > 0) {
                        continue;
                    }
                    $linkNode = $node->filterXPath("//a");
                    $pageLink = 'https:'.$linkNode->attr('href');
                    $title = $node->filterXPath("//h2|//span[@class='story-headline']")->text();

                    $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                    if ($this->isNeedSkipArticle($pageContent)) {
                        continue;
                    }
                    $html = new Crawler();
                    $html->addHtmlContent($pageContent->getBody()->getContents(), 'UTF-8');

                    $articlePubDate = $html->filterXPath("//meta[@property='article:published_time']")->first();

                    if ($articlePubDate->count()) {
                        $pubDateAttr = $articlePubDate->attr('content');
                    } else {
                        $dataArticle = $html->filterXPath(
                            "//script[@type='application/ld+json']")
                            ->last();

                        if (!$dataArticle->count()) {
                            continue;
                        }

                        $dataArticle = json_decode($dataArticle->text(), true);

                        if (!isset($dataArticle['@graph'][5]['datePublished'])) {
                            continue;
                        }

                        $pubDateAttr = $dataArticle['@graph'][5]['datePublished'];

                    }



                    $publicationDate = $this->createDateFromString($pubDateAttr);
                    $hashPreview = $this->previewHelper->getImageUrlHashFromList($node, '//img', 'src', 'https://www.leparisien.fr/');

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

    public function proxyEnablingAttempt(): ?int
    {
        return Guzzle::PROXY_ALWAYS_ENABLED;
    }
}
