<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\ca\Globalnews;

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
 * @package common\components\scrapers\sources\ca\Globalnews;
 *
 * @Config (
 * timezone="Canada/Atlantic", urls={
 * "https://globalnews.ca/world/",
 * "https://globalnews.ca/canada/",
 * "https://globalnews.ca/national/",
 * "https://globalnews.ca/politics/",
 * "https://globalnews.ca/money/",
 * "https://globalnews.ca/health/",
 * "https://globalnews.ca/entertainment/",
 * "https://globalnews.ca/lifestyle/",
 * "https://globalnews.ca/sports/"
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
            return $this->XPathParser->parseDescription($html, '//article//p[1]');
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//div[@class='l-content']";
            $articlesNode = $html->filterXPath($selector);

            $articles = $articlesNode->filterXPath("//li[@class='c-posts__item c-posts__loadmore']|//ul[contains(@class, 'c-posts c-posts c-posts--tile ')]//li|//li[@class='c-posts__item glide__slide']");
            $lastAddedPublicationTime = $this->lastPublicationTime;
            $lastAddedPublicationTime = $lastAddedPublicationTime->setTimezone($this->timezone);
            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {

                    $linkNode = $node->filterXPath('//a')->first();

                    if (!$linkNode->count()) {
                        continue;
                    }

                    $pageLink = $linkNode->attr('href');

                    $title = $node->filterXPath("//span[@class='c-posts__headlineText']")->first()->text();

                    $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                    if ($this->isNeedSkipArticle($pageContent)) {
                        continue;
                    }
                    $html = new Crawler();
                    $html->addHtmlContent($pageContent->getBody()->getContents(), 'UTF-8');

                    $dataArticle = $html->filterXPath("//script[@type='application/ld+json']")->first();

                    if (!$dataArticle->count()) {
                        continue;
                    }

                    $dataArticle = json_decode($dataArticle->text(), true);

                    $publicationDate = $this->createDateFromString($dataArticle['datePublished']);


                    $hashPreview = $this->previewHelper->getImageUrlHashFromList($node, '//img', 'data-src');

                    if ($publicationDate >= $lastAddedPublicationTime) {
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
