<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\it\Lastampa;

use common\components\scrapers\common\ArticleBodyScraper;
use common\components\scrapers\common\Config;
use common\components\scrapers\common\services\HashImageService;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\XPathParserV2;
use common\components\scrapers\dto\ArticleItem;
use GuzzleHttp\Promise\Coroutine;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Class Scraper
 * @package common\components\scrapers\sources\it\Lastampa;
 *
 * @Config (
 * timezone="Europe/Paris", urls={
 * "https://www.lastampa.it/casa-design",
 * "https://www.lastampa.it/montagna?refresh_ce",
 * "https://www.lastampa.it/cronaca",
 * "https://www.lastampa.it/cultura",
 * "https://www.lastampa.it/motori",
 * "https://www.lastampa.it/economia?refresh_ce",
 * "https://www.lastampa.it/politica",
 * "https://www.lastampa.it/esercizi-di-stile",
 * "https://www.lastampa.it/esteri?refresh_ce",
 * "https://www.lastampa.it/scienza?refresh_ce",
 * "https://www.lastampa.it/spettacoli?refresh_ce",
 * "https://www.lastampa.it/sport?refresh_ce",
 * "https://www.lastampa.it/tecnologia?refresh_ce",
 * "https://www.lastampa.it/la-zampa?refresh_ce",
 * "https://www.lastampa.it/mare?refresh_ce",
 * "https://www.lastampa.it/viaggi"
 * })
 */
class Scraper extends \common\components\scrapers\common\Scraper implements ArticleBodyScraper
{

    /**
     * @var HashImageService
     */
    private $hashImageService;

    /**
     * @var XPathParserV2
     */
    private $XPathParser;

    public function __construct(
        XPathParserV2 $XPathParser,
        HashImageService $hashImageService,
                      $config = []
    )
    {
        $this->hashImageService = $hashImageService;
        $this->XPathParser = $XPathParser;

        parent::__construct($config);
    }

    public function parseArticleBody(string $url): PromiseInterface
    {
        return $this->sendAsyncRequestWithProxy(new Request('GET', $url))->then(function (ResponseInterface $response) {
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');
            return $this->XPathParser->parseDescription($html, '//div[@class="story__text"]//p', false);
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//main";
            $articlesNode = $html->filterXPath($selector);

            $articles = $articlesNode->filterXPath("//article");

            $lastAddedPublicationTime = $this->lastPublicationTime;
            $lastAddedPublicationTime = $lastAddedPublicationTime->setTimezone($this->timezone);
            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    $linkNode = $node->filterXPath("//h2[@class='entry__title']//a")->first();
                    if (!$linkNode->count()) {
                        continue;
                    }
                    $pageLink = $linkNode->attr('href');

                    $title = $linkNode->text();
                    $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                    if ($this->isNeedSkipArticle($pageContent)) {
                        continue;
                    }
                    $html = new Crawler();
                    $html->addHtmlContent($pageContent->getBody()->getContents());

                    $dataArticle = $html->filterXPath(
                        "//script[@type='application/ld+json']")
                        ->first();

                    if (!$dataArticle->count()) {
                        continue;
                    }

                    $dataArticle = json_decode($dataArticle->text(), true);

                    if (!isset($dataArticle['isAccessibleForFree']) || $dataArticle['isAccessibleForFree'] == 'True') {
                        if (!isset($dataArticle['datePublished'])) {
                            continue;
                        }
                        $publicationDate = $this->createDateFromString($dataArticle['datePublished']);

                        $hashPreview = $this->hashImageService->hashImage($dataArticle['image']['url']);

                        if ($publicationDate > $lastAddedPublicationTime) {
                            $result[] = new ArticleItem($pageLink, $title, $publicationDate, $hashPreview);
                        }
                    }
                } catch (\Throwable $exception) {
                    $this->logArticleItemException($exception, $pageLink);
                }

            }

            yield $result;
        });
    }
}
