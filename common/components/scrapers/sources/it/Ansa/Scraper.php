<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\it\Ansa;

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
 * @package common\components\scrapers\sources\it\Ansa;
 *
 * @Config (
 * timezone="Europe/Paris", urls={
 * "https://www.ansa.it/sito/notizie/cronaca/cronaca.shtml",
 * "https://www.ansa.it/sito/notizie/politica/politica.shtml",
 * "https://www.ansa.it/sito/notizie/economia/economia.shtml",
 * "https://www.ansa.it/sito/notizie/mondo/mondo.shtml",
 * "https://www.ansa.it/sito/notizie/tecnologia/tecnologia.shtml",
 * "https://www.ansa.it/sito/notizie/sport/sport.shtml",
 * "https://www.ansa.it/sito/notizie/cultura/cultura.shtml",
 * "https://www.ansa.it/canale_ambiente/",
 * "https://www.ansa.it/canale_motori/",
 * "https://www.ansa.it/canale_saluteebenessere/",
 * "https://www.ansa.it/canale_scienza_tecnica/"
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
        XPathParserV2 $XPathParser,
        PreviewHelper $previewHelper,
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
            return $this->XPathParser->parseDescription($html, '//div[@itemprop="articleBody"]//p');
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//div[@class='pp-inner']|//div[contains(@class, 'container')]";
            $articlesNode = $html->filterXPath($selector);

            $articles = $articlesNode->filterXPath("//article");

            $lastAddedPublicationTime = $this->lastPublicationTime;
            $lastAddedPublicationTime = $lastAddedPublicationTime->setTimezone($this->timezone);
            $result = [];
            $baseUrl = 'https://www.ansa.it';
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {

                    if ($node->attr('class') === 'slider-container') {
                        continue;
                    }

                    $linkNode = $node->filterXPath("//a[not(contains(@href, 'javascript:void(0);'))]");
                    $pageLink = $baseUrl.$linkNode->attr('href');

                    $title = $node->filterXPath("//h3")->text();

                    $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                    if ($this->isNeedSkipArticle($pageContent)) {
                        continue;
                    }
                    $html = new Crawler();
                    $html->addHtmlContent($pageContent->getBody()->getContents(), 'UTF-8');

                    $articlePubDate = $html->filterXPath("//meta[@property='article:published_time']")->first();
                    if (!$articlePubDate->count()) {
                        continue;
                    }
                    $pubDateAttr = $articlePubDate->attr('content');

                    $publicationDate = $this->createDateFromString($pubDateAttr);
                    $hashPreview = $this->previewHelper->getOgImageUrlHash($html, 'sito/img/ico/ansa');

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
