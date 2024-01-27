<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\ca\Cbc;

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
 * @package common\components\scrapers\sources\ca\Cbc;
 *
 * @Config (
 * timezone="Canada/Atlantic", urls={
 * "https://www.cbc.ca/news/local",
 * "https://www.cbc.ca/news/thenational",
 * "https://www.cbc.ca/news/opinion",
 * "https://www.cbc.ca/news/world",
 * "https://www.cbc.ca/news/canada",
 * "https://www.cbc.ca/news/politics",
 * "https://www.cbc.ca/news/indigenous",
 * "https://www.cbc.ca/news/business",
 * "https://www.cbc.ca/news/health",
 * "https://www.cbc.ca/news/entertainment",
 * "https://www.cbc.ca/news/investigates",
 * "https://www.cbc.ca/news/gopublic",
 * "https://www.cbc.ca/sports"
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
            return $this->XPathParser->parseDescription($html, '//h2[@class="deck"]');
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//div[@id='app']";
            $articlesNode = $html->filterXPath($selector);

            $articles = $articlesNode->filterXPath("//a[contains(@class, 'cardRegular ')]|//a[contains(@class, 'cardListing ')]|//a[contains(@class, 'cardDefault ')]|//a[@class='contentWrapper']");
            $lastAddedPublicationTime = $this->lastPublicationTime;
            $lastAddedPublicationTime = $lastAddedPublicationTime->setTimezone($this->timezone);
            $result = [];
            $baseUrl = 'https://www.cbc.ca';
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {

                    $pageLink = trim($node->attr('href'));

                    if (!filter_var($pageLink, FILTER_VALIDATE_URL)) {
                        $pageLink = $baseUrl.$pageLink;
                    }

                    $title = $node->filterXPath("//h3[@class='headline']")->first()->text();

                    $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                    if ($this->isNeedSkipArticle($pageContent)) {
                        continue;
                    }
                    $html = new Crawler();
                    $html->addHtmlContent($pageContent->getBody()->getContents(), 'UTF-8');

                    $dataArticle = $html->filterXPath("//script[@type='application/ld+json']")->first();

                    if (!$dataArticle->count()) {
                        $dataArticle = $html->filterXPath("//meta[@property='video:release_date']")->first();
                        if (!$dataArticle->count()) {
                            continue;
                        }

                        $dateString = $dataArticle->attr('content');
                    }
                    else {
                        $dataArticle = json_decode($dataArticle->text(), true);
                        if (!isset($dataArticle['datePublished'])) {
                            continue;
                        }
                        else {
                            $dateString = $dataArticle['datePublished'];
                        }
                    }


                    $publicationDate = $this->createDateFromString($dateString);

                    $hashPreview = $this->previewHelper->getImageUrlHashFromList($node);

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
