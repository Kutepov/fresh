<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\gb\Aol;

use Carbon\Carbon;
use common\components\scrapers\common\ArticleBodyScraper;
use common\components\scrapers\common\helpers\PreviewHelper;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\XPathParserV2;
use common\components\scrapers\common\Config;
use common\components\scrapers\dto\ArticleItem;
use GuzzleHttp\Promise\Coroutine;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Class Scraper
 * @package common\components\scrapers\sources\gb\Aol;
 *
 * @Config (
 * timezone="Europe/London", urls={
 * "https://www.aol.co.uk/cars/",
 * "https://www.aol.co.uk/entertainment/",
 * "https://www.aol.co.uk/living/",
 * "https://www.aol.co.uk/news/politics/",
 * "https://www.aol.co.uk/sport/",
 * "https://www.aol.co.uk/news/uk/",
 * "https://www.aol.co.uk/news/world/"
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
            return $this->XPathParser->parseDescription($html, '//div[@class="caas-body"]//p[1]');
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//ul[contains(@class, 'stream-list-parent')]";
            $articlesNode = $html->filterXPath($selector);
            $articles = $articlesNode->filterXPath("//li[@class='stream-row stream-row-stream_item']|//li[@class='ntk-horizontal-list-item']|//ul[@class='ntk-lead-item-parent']/li");

            $lastAddedPublicationTime = $this->lastPublicationTime;
            $lastAddedPublicationTime = $lastAddedPublicationTime->setTimezone($this->timezone);
            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    $linkNode = $node->filterXPath("//a")->first();
                    if (!$linkNode->count()) {
                        continue;
                    }
                    $pageLink = $linkNode->attr('href');
                    $title = $node->filterXPath("//h3|//h2|//h5")->text();

                    $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                    if ($this->isNeedSkipArticle($pageContent)) {
                        continue;
                    }
                    $html = new Crawler();
                    $html->addHtmlContent($pageContent->getBody()->getContents(), 'UTF-8');


                    $pubdate = $html->filterXPath("//time[@class='aol-attr__author--time']")->text();
                    $publicationDate = $this->createDateFromString($pubdate);

                    if (!$publicationDate) {
                        continue;
                    }

                    $hashPreview = $this->previewHelper->getOgImageUrlHash($html);

                    if ($publicationDate > $lastAddedPublicationTime) {
                        $immutableDate = \DateTimeImmutable::createFromMutable($publicationDate);

                        $result[] = new ArticleItem($pageLink, $title, $immutableDate, $hashPreview);
                    }
                } catch (\Throwable $exception) {
                    $this->logArticleItemException($exception, $pageLink);
                }
            }

            yield $result;
        });
    }

    protected function getBaseUrl($url)
    {
        $paths = parse_url($url, PHP_URL_PATH);
        $paths = trim($paths, '/');
        $pathsArray = explode('/', $paths);

        return 'https://www.aol.co.uk/' . $pathsArray[0];
    }
}
