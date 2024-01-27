<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\fr\Lesechos;

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
 * @package common\components\scrapers\sources\fr\Lesechos;
 *
 * @Config (
 * timezone="Europe/Paris", urls={
 * "https://www.lesechos.fr/politique-societe",
 * "https://www.lesechos.fr/idees-debats",
 * "https://www.lesechos.fr/monde",
 * "https://www.lesechos.fr/tech-medias",
 * "https://www.lesechos.fr/industrie-services",
 * "https://www.lesechos.fr/pme-regions",
 * "https://www.lesechos.fr/economie-france",
 * "https://www.lesechos.fr/patrimoine",
 * "https://www.lesechos.fr/weekend"
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
        //TODO: Там вроде все статьи сделали по подписке.
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//section//div//div//div";
            $articlesNode = $html->filterXPath($selector);

            $articles = $articlesNode->filterXPath("//article");
            $lastAddedPublicationTime = $this->lastPublicationTime;
            $lastAddedPublicationTime = $lastAddedPublicationTime->setTimezone($this->timezone);
            $result = [];
            $baseUrl = 'https://www.lesechos.fr';
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    $linkNode = $node->filterXPath("//a");
                    $pageLink = $baseUrl.$linkNode->attr('href');
                    $title = $node->filterXPath("//h3")->text();

                    if (stripos($title, 'Sélection abonnés') !== false) {
                        continue;
                    }

                    $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                    if ($this->isNeedSkipArticle($pageContent)) {
                        continue;
                    }
                    $html = new Crawler();
                    $html->addHtmlContent($pageContent->getBody()->getContents(), 'UTF-8');

                    $articlePubDate = $html->filterXPath("//meta[@property='article:published_time']")->first();
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
