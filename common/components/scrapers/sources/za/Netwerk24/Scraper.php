<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\za\Netwerk24;

use common\components\scrapers\common\helpers\PreviewHelper;
use common\components\scrapers\common\Config;
use common\components\scrapers\dto\ArticleItem;
use GuzzleHttp\Promise\Coroutine;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Class Scraper
 * @package common\components\scrapers\sources\za\Netwerk24
 *
 * @Config (
 * timezone="Africa/Lusaka", urls={
 * "https://www.netwerk24.com/nuus",
 * "https://www.netwerk24.com/ontspan",
 * "https://www.netwerk24.com/sake",
 * "https://www.netwerk24.com/sport",
 * "https://www.netwerk24.com/stemme",
 * "https://www.netwerk24.com/vermaak"
 * })
 */
class Scraper extends \common\components\scrapers\common\Scraper
{
    /**
     * @var PreviewHelper
     */
    private $previewHelper;


    public function __construct(
        PreviewHelper $previewHelper,
        $config = []
    )
    {
        $this->previewHelper = $previewHelper;

        parent::__construct($config);
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//div[@class='container']";
            $articlesNode = $html->filterXPath($selector);
            $articles = $articlesNode->filterXPath("//article");
            $baseUrl = 'https://www.netwerk24.com/';
            $lastAddedPublicationTime = $this->lastPublicationTime;
            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    $linkNode = $node->filterXPath("//div[@class='article-item__title']//span");
                    if (!$linkNode->count()) {
                        continue;
                    }
                    $pageLink = $baseUrl.$node->filterXPath("//a[contains(@class, 'article-item--url')]")->attr('href');
                    $title = $linkNode->text();

                    $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                    if ($this->isNeedSkipArticle($pageContent)) {
                        continue;
                    }

                    $html = new Crawler();
                    $html->addHtmlContent($pageContent->getBody()->getContents(), 'UTF-8');
                    $articlePubDate = $html->filterXPath("//head//meta[@name='publisheddate']")->first();
                    if (!$articlePubDate->count()) {
                        continue;
                    }

                    $pubDateAttr = @$articlePubDate->attr('content');
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
