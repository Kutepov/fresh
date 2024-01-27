<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\pl\Polsatnews;

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
 * @package common\components\scrapers\sources\pl\Polsatnews
 *
 * @Config (
 * timezone="Europe/Warsaw", urls={
 * "https://www.polsatnews.pl/wiadomosci/polska/",
 * "https://www.polsatnews.pl/wiadomosci/swiat/",
 * "https://www.polsatnews.pl/wiadomosci/biznes/",
 * "https://www.polsatnews.pl/wiadomosci/technologie/",
 * "https://www.polsatnews.pl/wiadomosci/kultura/"
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
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');
            return $this->XPathParser->parseDescription($html, '//div[@class="news__preview"]//p[1]');
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();

            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//div[@class='inside inside--container']";
            $articlesNode = $html->filterXPath($selector);

            $articles = $articlesNode->filterXPath("//article[contains(@class, 'news')]");
            $lastAddedPublicationTime = $this->lastPublicationTime;

            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    $pageLink = $node->filterXPath("//a")->first()->attr('href');
                    $title = $node->filterXPath("//h2|//h1")->first()->text();

                    $innerPageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                    if ($this->isNeedSkipArticle($innerPageContent)) {
                        continue;
                    }
                    $html = new Crawler();
                    $html->addHtmlContent($innerPageContent->getBody()->getContents(), 'UTF-8');

                    $pubDateNode = $html->filterXPath("//time[@class='news__time']")->first();
                    if (!$pubDateNode->count()) {
                        continue;
                    }
                    $pubDateAttr = $pubDateNode->attr('datetime');

                    $publicationDate = $this->createDateFromString($pubDateAttr);

                    $hashPreview = $this->previewHelper->getOgImageUrlHash($html);
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
