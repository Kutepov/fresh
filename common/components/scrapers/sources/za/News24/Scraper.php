<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\za\News24;

use common\components\scrapers\common\helpers\PreviewHelper;
use common\components\scrapers\common\symfony\SiteParserModule\Infrastructure\SelectorsRemover;
use common\components\scrapers\common\Config;
use common\components\scrapers\dto\ArticleItem;
use GuzzleHttp\Promise\Coroutine;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Class Scraper
 * @package common\components\scrapers\sources\za\News24
 *
 * @Config (
 * timezone="Africa/Lusaka", urls={
 * "https://www.news24.com/news24/africa",
 * "https://www.news24.com/fin24",
 * "https://www.news24.com/news24/lifestyle",
 * "https://www.news24.com/news24",
 * "https://www.news24.com/news24/opinions",
 * "https://www.news24.com/news24/southafrica",
 * "https://www.news24.com/news24/video",
 * "https://www.news24.com/sport",
 * "https://www.news24.com/news24/world"
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

            $selector = "//div[contains(@class, 'tf-lhs-col')]";
            $articlesNode = $html->filterXPath($selector);
            $articles = $articlesNode->filterXPath("//div[contains(@class, 'article-list tf-grid')]//article[contains(@class, 'article-item')]");
            $baseUrl = 'https://www.news24.com';

            $lastAddedPublicationTime = $this->lastPublicationTime;
            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    $linkNode = $node->filterXPath('//a')->first();
                    $pageLink = $linkNode->attr('href');

                    if (!preg_match('#^https?://#i', $pageLink)) {
                        $pageLink = $baseUrl . $pageLink;
                    }

                    $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                    if ($this->isNeedSkipArticle($pageContent)) {
                        continue;
                    }

                    $html = new Crawler();
                    $html->addHtmlContent($pageContent->getBody()->getContents(), 'UTF-8');
                    $titleNode = $html->filterXPath("//h1[@class='article__title']");
                    if (!$titleNode->count()) {
                        continue;
                    }
                    $title = $titleNode->text();
                    $articlePubDate = $html->filterXPath("//meta[@name='publisheddate']")->first();
                    $pubDateAttr = $articlePubDate->attr('content');

                    if (!$pubDateAttr) {
                        continue;
                    }
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
