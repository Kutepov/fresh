<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\us\Nbcwashington;

use common\components\guzzle\Guzzle;
use common\components\scrapers\common\ArticleBodyScraper;
use common\components\scrapers\common\helpers\PreviewHelper;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\XPathParserV2;
use common\components\scrapers\dto\ArticleItem;
use GuzzleHttp\Promise\Coroutine;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DomCrawler\Crawler;
use common\components\scrapers\common\Config;

/**
 * Class Scraper
 * @package common\components\scrapers\sources\us\Nbcwashington
 *
 * @Config (timezone="America/New_York", urls={
 * "https://www.nbcwashington.com/news/local/inequality-in-america/",
 * "https://www.nbcwashington.com/lx/",
 * "https://www.nbcwashington.com/investigations/",
 * "https://www.nbcwashington.com/news/sports/nbcsports/",
 * "https://www.nbcwashington.com/news/national-international/",
 * "https://www.nbcwashington.com/news/health/"
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
            return $this->XPathParser->parseDescription($html, '//div[@class="page-article-body"]//p[1]');
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler($pageContent->getBody()->getContents());

            $selector = "//main[@id='main']";
            $articlesNode = $html->filterXPath($selector)->first();

            $articles = $articlesNode->filterXPath("//div[@class='story-card story-card__image-on-top']|//li[@class='story-card__list-item story-card__list-item--image-on-top']|//li[@class='story-card story-card__list-item story-card__list-item--post-river']");
            $lastAddedPublicationTime = $this->lastPublicationTime;

            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    $linkNode = $node->filterXPath('//h3[@class="story-card__title"]//a')->first();
                    if ($linkNode->count() === 0) {
                        continue;
                    }
                    $pageLink = $linkNode->attr('href');
                    $title = $linkNode->text();

                    $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                    if ($this->isNeedSkipArticle($pageContent)) {
                        continue;
                    }
                    $html = new Crawler($pageContent->getBody()->getContents());

                    $dataArticle = $html->filterXPath("//time[@class='entry-date published']")->first();

                    if (!$dataArticle->count()) {
                        continue;
                    }

                    $pubDateAttr = $dataArticle->attr('datetime');

                    $publicationDate = $this->createDateFromString($pubDateAttr);
                    $hashPreview = $this->previewHelper->getImageUrlHashFromList($node);

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

    public function proxyEnablingAttempt(): ?int
    {
        return Guzzle::PROXY_ALWAYS_ENABLED;
    }
}