<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\gb\Huffingtonpost;

use Carbon\Carbon;
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
 * @package common\components\scrapers\sources\gb\Huffingtonpost;
 *
 * @Config (
 * timezone="Europe/London", urls={
 * "https://www.huffingtonpost.co.uk/news/",
 * "https://www.huffingtonpost.co.uk/news/coronavirus/",
 * "https://www.huffingtonpost.co.uk/politics/",
 * "https://www.huffingtonpost.co.uk/news/opinion/",
 * "https://www.huffingtonpost.co.uk/news/personal/",
 * "https://www.huffingtonpost.co.uk/entertainment/"
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
            return $this->XPathParser->parseDescription($html, '//div[@class="dek"]');
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//div[@class='a-page__content a-page__content--twilight']|//div[@class='a-page__content a-page__content--bottom js-page-content-top']";
            $articlesNode = $html->filterXPath($selector);
            $articles = $articlesNode->filterXPath("//div[contains(@class, 'card card--standard')]|//div[@class='card']");

            $lastAddedPublicationTime = $this->lastPublicationTime;
            $lastAddedPublicationTime = $lastAddedPublicationTime->setTimezone($this->timezone);
            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    $linkNode = $node->filterXPath("//div[@class='card__text']//a")->first();
                    $pageLink = $linkNode->attr('href');
                    $title = $linkNode->text();

                    $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                    if ($this->isNeedSkipArticle($pageContent)) {
                        continue;
                    }
                    $html = new Crawler();
                    $html->addHtmlContent($pageContent->getBody()->getContents(), 'UTF-8');


                    $pubdate = $html->filterXPath("//meta[@property='article:published_time']")->attr('content');
                    $publicationDate = $this->createDateFromString($pubdate);

                    if (!$publicationDate) {
                        continue;
                    }

                    $hashPreview = $this->previewHelper->getImageUrlHashFromList($node);

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

    private function getDatePublished(Crawler $script): ?Carbon
    {
        preg_match("/\"datePublished\"\:\s\"([a-zA-z\\'\/0-9\.\-\:]*)\"/", $script->text(), $output_array);
        if (isset($output_array[1])) {
            $date = $output_array[1];

            return $this->createDateFromString($date);
        }
        return null;
    }
}
