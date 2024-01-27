<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\es\Antena3;

use common\components\scrapers\common\Config;
use common\components\scrapers\common\helpers\PreviewHelper;
use common\components\scrapers\common\services\HashImageService;
use common\components\scrapers\dto\ArticleBody;
use common\components\scrapers\dto\ArticleBodyNode;
use common\components\scrapers\dto\ArticleItem;
use GuzzleHttp\Promise\Coroutine;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Class Scraper
 * @package common\components\scrapers\sources\Antena3
 *
 * @Config (
 * timezone="Europe/Madrid", urls={
 * "https://www.antena3.com/noticias/rss/4013050.xml",
 * "https://www.antena3.com/noticias/rss/4043764.xml",
 * "https://www.antena3.com/rss/350056.xml",
 * "https://www.antena3.com/noticias/rss/4045150.xml",
 * "https://www.antena3.com/noticias/rss/4043853.xml",
 * "https://www.antena3.com/noticias/rss/4045125.xml",
 * "https://www.antena3.com/noticias/rss/4043743.xml",
 * "https://www.antena3.com/noticias/rss/4043815.xml",
 * "https://www.antena3.com/noticias/rss/4043795.xml",
 * "https://www.antena3.com/noticias/rss/4043741.xml",
 * "https://www.antena3.com/noticias/rss/4043823.xml",
 * "https://www.antena3.com/rss/3350605.xml"
 * })
 */
class Scraper extends \common\components\scrapers\common\Scraper
{

    /**
     * @var HashImageService
     */
    private $hashImageService;

    /**
     * @var PreviewHelper
     */
    private $previewHelper;

    public function __construct(
        HashImageService $hashImageService,
        PreviewHelper    $previewHelper,
                         $config = []
    )
    {
        $this->hashImageService = $hashImageService;
        $this->previewHelper = $previewHelper;

        parent::__construct($config);
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $articles = $html->filterXPath("//channel//item");
            $lastAddedPublicationTime = $this->lastPublicationTime->setTimezone($this->timezone);

            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    if ($node->filterXPath('//category')->text() === 'Loterías') {
                        continue;
                    }

                    $title = $node->filterXPath('//title')->text();
                    $pubDateAttr = $node->filterXPath('//pubDate|//pubdate')->text();
                    $pageLink = $node->filterXPath('//guid')->text();

                    $thumbnail = $node->filterXPath("//*[local-name()='thumbnail']")->count() ? $node->filterXPath("//*[local-name()='thumbnail']")->attr('url') : false;
                    if ($thumbnail) {
                        $hashPreview = $this->hashImageService->hashImage($thumbnail);
                    } else {
                        $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));

                        $html = new Crawler();
                        $html->addHtmlContent($pageContent->getBody()->getContents(), 'UTF-8');
                        $hashPreview = $this->previewHelper->getOgImageUrlHash($html);
                    }

                    $publicationDate = $this->createDateFromString($pubDateAttr);

                    if ($publicationDate > $lastAddedPublicationTime) {
                        $article = new ArticleItem($pageLink, $title, $publicationDate, $hashPreview);

                        $body = new ArticleBody();
                        $body->add(new ArticleBodyNode('paragraph', $node->filterXPath('//description')->text()));
                        $article->setBody($body);

                        $result[] = $article;
                    }
                } catch (\Throwable $exception) {
                    $this->logArticleItemException($exception, $pageLink);
                }
            }

            yield $result;
        });
    }
}
