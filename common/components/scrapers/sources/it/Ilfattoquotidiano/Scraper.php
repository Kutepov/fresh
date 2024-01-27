<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\it\Ilfattoquotidiano;

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
 * @package common\components\scrapers\sources\it\Ilfattoquotidiano;
 *
 * @Config (
 * timezone="Europe/Paris", urls={
 * "https://www.ilfattoquotidiano.it/politica-palazzo/",
 * "https://www.ilfattoquotidiano.it/giustizia-impunita/",
 * "https://www.ilfattoquotidiano.it/media-regime/",
 * "https://www.ilfattoquotidiano.it/economia-lobby/",
 * "https://www.ilfattoquotidiano.it/zona-euro/",
 * "https://www.ilfattoquotidiano.it/lavoro-precari/",
 * "https://www.ilfattoquotidiano.it/cronaca/",
 * "https://www.ilfattoquotidiano.it/cronaca/memoriale-coronavirus/",
 * "https://www.ilfattoquotidiano.it/cronaca-nera/",
 * "https://www.ilfattoquotidiano.it/mafie/",
 * "https://www.ilfattoquotidiano.it/mondo/",
 * "https://www.ilfattoquotidiano.it/ambiente-veleni/",
 * "https://www.ilfattoquotidiano.it/sport-miliardi/",
 * "https://www.ilfattoquotidiano.it/scuola/",
 * "https://www.ilfattoquotidiano.it/diritti/",
 * "https://www.ilfattoquotidiano.it/societa/",
 * "https://www.ilfattoquotidiano.it/tecnologia/",
 * "https://www.ilfattoquotidiano.it/scienza/",
 * "https://www.ilfattoquotidiano.it/motori-2-0/"
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
            return $this->XPathParser->parseDescription($html, '//section[@class="catenaccio"]//p', false);
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//section[@class='main-container']";
            $articlesNode = $html->filterXPath($selector);

            $articles = $articlesNode->filterXPath("//div[contains(@class, 'rullo-item')]|//div[contains(@class, 'categoria-primo-piano categoria-primo-piano-full wrapper-article')]|//div[contains(@class, 'single-item-small')]");

            $lastAddedPublicationTime = $this->lastPublicationTime;
            $lastAddedPublicationTime = $lastAddedPublicationTime->setTimezone($this->timezone);
            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    $linkNode = $node->filterXPath("//a");
                    $pageLink = $linkNode->attr('href');

                    $title = $node->filterXPath("//h4|//h3|//h2|//p[@class='p-item']")->text();

                    $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                    if ($this->isNeedSkipArticle($pageContent)) {
                        continue;
                    }
                    $html = new Crawler();
                    $html->addHtmlContent($pageContent->getBody()->getContents(), 'UTF-8');

                    if ($html->filterXPath('//meta[@property="k5a:paid"]')->count()) {
                        if ($html->filterXPath('//meta[@property="k5a:paid"]')->attr('content') === '1') {
                            continue;
                        }
                    }

                    $articlePubDate = $html->filterXPath("//meta[@property='article:published_time']")->first();
                    if (!$articlePubDate->count()) {
                        continue;
                    }
                    $pubDateAttr = $articlePubDate->attr('content');

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
}
