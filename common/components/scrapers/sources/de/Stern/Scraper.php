<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\de\Stern;

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
 * @package common\components\scrapers\sources\de\Stern;
 *
 * @Config (
 * timezone="Europe/Berlin", urls={
 * "https://www.stern.de/panorama/",
 * "https://www.stern.de/politik/",
 * "https://www.stern.de/kultur/",
 * "https://www.stern.de/lifestyle/",
 * "https://www.stern.de/digital/",
 * "https://www.stern.de/wirtschaft/",
 * "https://www.stern.de/sport/",
 * "https://www.stern.de/gesundheit/",
 * "https://www.stern.de/genuss/",
 * "https://www.stern.de/reise/",
 * "https://www.stern.de/familie/",
 * "https://www.stern.de/auto/"
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
            return $this->XPathParser->parseDescription($html, '//div[@class="intro"]');
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//div[@class='index page__inner-content']";
            $articlesNode = $html->filterXPath($selector);

            $articles = $articlesNode->filterXPath("//article[contains(@class, 'teaser teaser--')]");

            $lastAddedPublicationTime = $this->lastPublicationTime;
            $lastAddedPublicationTime = $lastAddedPublicationTime->setTimezone($this->timezone);
            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {

                    $linkNode = $node->filterXPath("//a");
                    if (!$linkNode->count()) {
                        continue;
                    }

                    $pageLink = $linkNode->attr('href');

                    $title = $node->filterXPath("//span[contains(@class, 'u-typo--teaser-title-tg4')]|//span[contains(@class, 'u-typo--teaser-title-tg5')]|//h3[contains(@class, 'teaser__headline')]")->first()->text();

                    if ($node->filterXPath("//div[contains(@class, 'teaser__brand-identifier--str_crime')]|//div[@class='sis-rectangleSternPlus__logo']|//div[contains(@class, 'teaser__brand-identifier--str_plus')]")->count()) {
                        continue;
                    }

                    $pubDateAttrNode = $node->filterXPath("//time[contains(@class, 'teaser__date')]")->first();
                    $hashPreview = $this->previewHelper->getImageUrlHashFromList($node);

                    $html = null;
                    if (!$hashPreview) {
                        $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));

                        $html = new Crawler();
                        $html->addHtmlContent($pageContent->getBody()->getContents(), 'UTF-8');

                        $hashPreview = $this->previewHelper->getOgImageUrlHash($html);

                    }

                    if ($pubDateAttrNode->count()) {
                        $pubDateAttr = $pubDateAttrNode->attr('datetime');
                    }
                    else {
                        if (!$html) {
                            $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));

                            $html = new Crawler();
                            $html->addHtmlContent($pageContent->getBody()->getContents(), 'UTF-8');
                        }

                        $dataArticle = $html->filterXPath(
                            "//meta[@name='date']")
                            ->first();

                        if (!$dataArticle->count()) {
                            continue;
                        }

                        $pubDateAttr = $dataArticle->attr('content');
                    }

                    $publicationDate = $this->createDateFromString($pubDateAttr);

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
