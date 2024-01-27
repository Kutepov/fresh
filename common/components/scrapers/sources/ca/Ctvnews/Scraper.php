<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\ca\Ctvnews;

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
 * @package common\components\scrapers\sources\ca\Ctvnews;
 *
 * @Config (
 * timezone="Canada/Atlantic", urls={
 * "https://www.ctvnews.ca/health/coronavirus/",
 * "https://www.ctvnews.ca/climate-and-environment",
 * "https://www.ctvnews.ca/business",
 * "https://www.ctvnews.ca/autos",
 * "https://www.ctvnews.ca/canada",
 * "https://www.ctvnews.ca/sci-tech",
 * "https://www.ctvnews.ca/world",
 * "https://www.ctvnews.ca/health",
 * "https://www.ctvnews.ca/entertainment",
 * "https://www.ctvnews.ca/sports",
 * "https://www.ctvnews.ca/politics",
 * "https://www.ctvnews.ca/lifestyle"
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
            return $this->XPathParser->parseDescription($html, '//div[@class="c-text"]//p[1]');
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//div[@class='content']|//div[@class='root responsivegrid']";
            $articlesNode = $html->filterXPath($selector);

            $articles = $articlesNode->filterXPath("//ul[contains(@class, 'linklist')]//div[contains(@class, 'teaser-image-wrapper')]|//ul[contains(@class, 'linklist')]/li/a|//li[@class='c-list__item']");

            $lastAddedPublicationTime = $this->lastPublicationTime;
            $lastAddedPublicationTime = $lastAddedPublicationTime->setTimezone($this->timezone);
            $baseUrl = 'https://www.ctvnews.ca';
            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {

                    if ($node->nodeName() === 'a') {
                        $pageLink = $node->attr('href');
                        $title = $node->text();
                    }
                    else {
                        $linkNode = $node->filterXPath('//a')->first();
                        $pageLink = $linkNode->attr('href');
                        $title = $node->filterXPath("//h2[@class='teaserTitle']|//h3")->first()->text();
                    }

                    if (!filter_var($pageLink, FILTER_VALIDATE_URL)) {
                        $pageLink = $baseUrl.$pageLink;
                    }


                    $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                    if ($this->isNeedSkipArticle($pageContent)) {
                        continue;
                    }
                    $html = new Crawler();
                    $html->addHtmlContent($pageContent->getBody()->getContents(), 'UTF-8');

                    if (!$html->filterXPath('//meta[@property="article:content_tier"]')->count()) {
                        continue;
                    } elseif ($html->filterXPath('//meta[@property="article:content_tier"]')->attr('content') !== 'free') {
                        continue;
                    }

                    $dataArticle = $html->filterXPath("//meta[@property='article:published_time']")->first();

                    if (!$dataArticle->count()) {
                        continue;
                    }

                    $publicationDate = $this->createDateFromString($dataArticle->attr('content'));


                    $hashPreview = $this->previewHelper->getImageUrlHashFromList($node);
                    if (!$hashPreview) {
                        $hashPreview = $this->previewHelper->getOgImageUrlHash($html);
                    }

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
