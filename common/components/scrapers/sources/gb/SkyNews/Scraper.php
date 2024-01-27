<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\gb\SkyNews;

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
 * @package common\components\scrapers\sources\gb\SkyNews;
 *
 * @Config (
 * timezone="Europe/London", urls={
 * "https://news.sky.com/analysis",
 * "https://news.sky.com/business",
 * "https://news.sky.com/climate",
 * "https://news.sky.com/entertainment",
 * "https://news.sky.com/opinion",
 * "https://news.sky.com/politics",
 * "https://news.sky.com/strangenews",
 * "https://news.sky.com/technology",
 * "https://news.sky.com/travel",
 * "https://news.sky.com/uk",
 * "https://news.sky.com/us",
 * "https://news.sky.com/videos",
 * "https://news.sky.com/world"
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
            return $this->XPathParser->parseDescription($html, '//div[@class="sdc-article-header__titles"]//p');
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = '//body';
            $articlesNode = $html->filterXPath($selector);

            $this->selectorsRemover->remove("//div[contains(@class, 'sdc-site-au')]", $articlesNode);
            $articles = $articlesNode->filterXPath("//div[contains(concat(' ', @class, ' '), ' sdc-site-tiles__item ')]");

            $lastAddedPublicationTime = $this->lastPublicationTime;
            $lastAddedPublicationTime = $lastAddedPublicationTime->setTimezone($this->timezone);
            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    $linkNode = $node->filterXPath("//a[contains(@class, 'sdc-site-tile__headline-link')]");
                    if (!$linkNode->count()) {
                        continue;
                    }
                    $pageLink = $linkNode->attr('href');
                    if (!preg_match('#^https://#i', $pageLink)) {
                        $pageLink = 'https://news.sky.com' . $pageLink;
                    }
                    $title = $linkNode->text();

                    $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                    if ($this->isNeedSkipArticle($pageContent)) {
                        continue;
                    }
                    $html = new Crawler();
                    $html->addHtmlContent($pageContent->getBody()->getContents(), 'UTF-8');


                    $scriptWithDate = $html->filterXPath("//script[contains(text(), 'datePublished')]");
                    if (!$scriptWithDate->count()) {
                        continue;
                    }
                    $publicationDate = $this->getDatePublished($scriptWithDate);
                    if (!$publicationDate) {
                        continue;
                    }

                    $hashPreview = $this->previewHelper->getOgImageUrlHash($html, 'skynews-brexit-breaking-news');

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
