<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\de\Sueddeutsche;

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
 * @package common\components\scrapers\sources\de\Sueddeutsche;
 *
 * @Config (
 * timezone="Europe/Berlin", urls={
 * "https://www.sueddeutsche.de/thema/Coronavirus",
 * "https://www.sueddeutsche.de/politik",
 * "https://www.sueddeutsche.de/wirtschaft",
 * "https://www.sueddeutsche.de/meinung",
 * "https://www.sueddeutsche.de/panorama",
 * "https://www.sueddeutsche.de/sport",
 * "https://www.sueddeutsche.de/muenchen",
 * "https://www.sueddeutsche.de/bayern",
 * "https://www.sueddeutsche.de/kultur",
 * "https://www.sueddeutsche.de/leben",
 * "https://www.sueddeutsche.de/wissen",
 * "https://www.sueddeutsche.de/reise",
 * "https://www.sueddeutsche.de/auto"
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
            return $this->XPathParser->parseDescription($html, '//div[@itemprop="articleBody"]//p[1]');
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//div[@id='wrapper']";
            $articlesNode = $html->filterXPath($selector);

            $articles = $articlesNode->filterXPath("//a[@class='sz-banderole-3-img__teaser-link']|//a[@class='sz-teaser']");

            $lastAddedPublicationTime = $this->lastPublicationTime;
            $lastAddedPublicationTime = $lastAddedPublicationTime->setTimezone($this->timezone);
            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {

                    $pageLink = $node->attr('href');

                    $title = $node->filterXPath("//p[@class='sz-banderole-3-img__img-title']|//h3[contains(@class, 'sz-teaser__title')]")->first()->text();

                    if ($node->filterXPath("//title[contains(text(), 'SZ Plus')]")->count()) {
                        continue;
                    }

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
                    $hashPreview = $this->previewHelper->getImageUrlHashFromList($node, '//img', 'data-src');

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
