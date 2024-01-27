<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\br\Noticias;

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
 * @package common\components\scrapers\sources\br\Noticias;
 *
 * @Config (
 * timezone="America/Sao_Paulo", urls={
 * "https://noticias.uol.com.br/confere",
 * "https://noticias.uol.com.br/cotidiano",
 * "https://noticias.uol.com.br/internacional",
 * "https://noticias.uol.com.br/politica",
 * "https://noticias.uol.com.br/saude",
 * "https://noticias.uol.com.br/ultimas",
 * "https://economia.uol.com.br/",
 * "https://www.uol.com.br/carros/",
 * "https://www.uol.com.br/esporte/",
 * "https://www.uol.com.br/splash/",
 * "https://www.uol.com.br/tilt/"
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
            $html->addHtmlContent($response->getBody()->getContents());
            return $this->XPathParser->parseDescription($html, '//div[contains(@class, "text ")]//p[1]');
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//section[contains(@class, 'list')]|//div[@class='container']";
            $articlesNode = $html->filterXPath($selector);
            $this->selectorsRemover->remove(
                "//div[contains(@class, 'itemAds')]",
                $articlesNode
            );
            $articles = $articlesNode->filterXPath("//div[contains(@class, 'flex-wrap ')]//div[contains(@class, 'thumbnails-item')]|//div[contains(@class, 'thumbnail-standard-wrapper')]|//ul[contains(@class, 'relacionadas')]//li");

            $lastAddedPublicationTime = $this->lastPublicationTime;
            $lastAddedPublicationTime = $lastAddedPublicationTime->setTimezone($this->timezone);
            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    $linkNode = $node->filterXPath("//div[contains(@class, 'thumbnails-wrapper')]//a|//a");
                    $pageLink = $linkNode->attr('href');
                    $pageLink = preg_replace('#^http://#', 'https://', $pageLink);
                    $titleNode = $node->filterXPath("//h3[contains(@class, 'thumb-title')]|//h2|//a");
                    $this->selectorsRemover->remove('//time', $titleNode);
                    $this->selectorsRemover->remove('//span', $titleNode);
                    $title = $titleNode->text();

                    if (!$node->filterXPath("//time[@class='thumb-date']")->count()) {
                        $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                        if ($this->isNeedSkipArticle($pageContent)) {
                            continue;
                        }
                        $html = new Crawler();
                        $html->addHtmlContent($pageContent->getBody()->getContents(), 'UTF-8');
                        $articlePubDate = $html->filterXPath("//p[contains(@class, 'p-author time')]");

                        if (!$articlePubDate->count()) {
                            continue;
                        }

                        $pubDateAttr = $articlePubDate->attr('ia-date-publish');

                        $publicationDate = $this->createDateFromString($pubDateAttr);
                        $hashImage = $this->previewHelper->getOgImageUrlHash($html, 'default-share/noticias');
                    } else {
                        $dateString = $this->prepareDateString($node->filterXPath("//time[@class='thumb-date']")->first()->text());
                        $publicationDate = $this->createDateFromString($dateString);
                        $hashImage = $this->previewHelper->getImageUrlHashFromList($node, "//img", 'data-src', '', 'placeholder-image');
                    }

                    if ($publicationDate > $lastAddedPublicationTime) {
                        $result[] = new ArticleItem($pageLink, $title, $publicationDate, $hashImage);
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
        $string = str_replace('/', '-', $string);
        return str_replace('h', ':', $string);
    }

}
