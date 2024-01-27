<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\de\Faz;

use common\components\scrapers\common\ArticleBodyScraper;
use common\components\scrapers\common\helpers\PreviewHelper;
use common\components\scrapers\common\Config;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\XPathParserV2;
use common\components\scrapers\common\symfony\SiteParserModule\Infrastructure\SelectorsRemover;
use common\components\scrapers\dto\ArticleItem;
use GuzzleHttp\Promise\Coroutine;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Class Scraper
 * @package common\components\scrapers\sources\de\Faz;
 *
 * @Config (
 * timezone="Europe/Berlin", urls={
 * "https://www.faz.net/aktuell/politik/",
 * "https://www.faz.net/aktuell/wirtschaft/",
 * "https://www.faz.net/aktuell/finanzen/",
 * "https://www.faz.net/aktuell/feuilleton/",
 * "https://www.faz.net/aktuell/karriere-hochschule/",
 * "https://www.faz.net/aktuell/gesellschaft/",
 * "https://www.faz.net/aktuell/stil/",
 * "https://www.faz.net/aktuell/rhein-main/",
 * "https://www.faz.net/aktuell/technik-motor/",
 * "https://www.faz.net/aktuell/wissen/",
 * "https://www.faz.net/aktuell/reise/"
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
    private $selectorRemover;

    /**
     * @var XPathParserV2
     */
    private $XPathParser;

    public function __construct(
        PreviewHelper    $previewHelper,
        SelectorsRemover $selectorsRemover,
        XPathParserV2    $XPathParser,
                         $config = []
    )
    {
        $this->previewHelper = $previewHelper;
        $this->selectorRemover = $selectorsRemover;
        $this->XPathParser = $XPathParser;

        parent::__construct($config);
    }

    public function parseArticleBody(string $url): PromiseInterface
    {
        return $this->sendAsyncRequestWithProxy(new Request('GET', $url))->then(function (ResponseInterface $response) {
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents());
            return $this->XPathParser->parseDescription($html, '//p[@id="pageIndex_1"]');
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $this->selectorRemover->remove("//div[@class='mm-MmBox mm-MmBox-is-inverted']", $html);

            $selector = "//div[@id='TOP']";
            $articlesNode = $html->filterXPath($selector);

            $articles = $articlesNode->filterXPath("//article[contains(@class, 'js-tsr-Base')]");

            $lastAddedPublicationTime = $this->lastPublicationTime;
            $lastAddedPublicationTime = $lastAddedPublicationTime->setTimezone($this->timezone);
            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {

                    $linkNode = $node->filterXPath("//a[contains(@class, 'js-tsr-Base_ContentLink')]");
                    if (!$linkNode->count()) {
                        continue;
                    }

                    $pageLink = $linkNode->attr('href');

                    $title = $node->filterXPath("//header//span[contains(@class, 'tsr-Base_HeadlineText')]")->first()->text();

                    if ($node->filterXPath("//span[contains(@class, 'ico-Base_FazPlus')]")->count()) {
                        continue;
                    }

                    $pubDateAttrNode = $node->filterXPath("//time[contains(@class, 'tsr-Base_ContentMetaTime')]")->first();

                    if ($pubDateAttrNode->count()) {
                        $pubDateAttr = $pubDateAttrNode->attr('datetime');
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
                    $hashPreview = $this->previewHelper->getImageUrlHashFromList($node, "//img", 'data-retina-src');

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
