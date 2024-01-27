<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\de\Morgenpost;

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
 * @package common\components\scrapers\sources\de\Morgenpost;
 *
 * @Config (
 * timezone="Europe/Berlin", urls={
 * "https://www.morgenpost.de/berlin/",
 * "https://www.morgenpost.de/bezirke/",
 * "https://www.morgenpost.de/politik/",
 * "https://www.morgenpost.de/wirtschaft/",
 * "https://www.morgenpost.de/sport/",
 * "https://www.morgenpost.de/vermischtes/",
 * "https://www.morgenpost.de/kultur/",
 * "https://www.morgenpost.de/web-wissen/",
 * "https://www.morgenpost.de/reise/",
 * "https://www.morgenpost.de/lifestyle/"
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
            return $this->XPathParser->parseDescription($html, '//p[@class="article__header__intro__text"]');
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//body";
            $articlesNode = $html->filterXPath($selector);

            $articles = $articlesNode->filterXPath("//article");
            $lastAddedPublicationTime = $this->lastPublicationTime;
            $lastAddedPublicationTime = $lastAddedPublicationTime->setTimezone($this->timezone);
            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {

                    if ($node->filterXPath("//span[contains(@class, 'ispayedcontent')]")->count()) {
                        continue;
                    }

                    $linkNode = $node->filterXPath("//a")->first();
                    if (!$linkNode->count()) {
                        continue;
                    }

                    $pageLink = $linkNode->attr('href');

                    $titleNode = $node->filterXPath("//span[contains(@class, 'teaser__headline')]")->first();

                    if (!$titleNode->count()) {
                        continue;
                    }

                    $title = $titleNode->text();

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


                    $publicationDate = $this->createDateFromString($pubDateAttr);
                    $hashPreview = $this->previewHelper->getImageUrlHashFromList($node, '//source', 'srcset');
                    if (!$hashPreview) {
                        $hashPreview = $this->previewHelper->getOgImageUrlHash($html);
                    }

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
