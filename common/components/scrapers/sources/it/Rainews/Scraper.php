<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\it\Rainews;

use common\components\scrapers\common\ArticleBodyScraper;
use common\components\scrapers\common\Config;
use common\components\scrapers\common\services\HashImageService;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\XPathParserV2;
use common\components\scrapers\dto\ArticleItem;
use GuzzleHttp\Promise\Coroutine;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Class Scraper
 * @package common\components\scrapers\sources\it\Rainews;
 *
 * @Config (
 * timezone="Europe/Paris", urls={
 * "https://www.rainews.it/archivio/ambiente",
 * "https://www.rainews.it/archivio/artiespettacolo",
 * "https://www.rainews.it/archivio/cronaca",
 * "https://www.rainews.it/archivio/esteri",
 * "https://www.rainews.it/archivio/economiaefinanza",
 * "https://www.rainews.it/archivio/politica",
 * "https://www.rainews.it/archivio/salute",
 * "https://www.rainews.it/archivio/scienzaetecnologia",
 * "https://www.rainews.it/archivio/societa",
 * "https://www.rainews.it/archivio/sport",
 * "https://www.rainews.it/archivio/stilidivitaetempolibero",
 * "https://www.rainews.it/archivio/viaggieturismo"
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
            return $this->XPathParser->parseDescription($html, '//h2[@class="article__subtitle"]');
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $rainewsArchive = $html->filterXPath('//rainews-archive')->first();
            $rainewsArchive->attr('domain');

            $json = json_decode($rainewsArchive->attr('tematiche'), true);

            $response = yield $this->sendAsyncRequestWithProxy(new Request('POST', 'https://www.rainews.it/atomatic/news-search-service/api/v3/search'), [
                RequestOptions::HEADERS => [
                    'Host' => 'www.rainews.it'
                ],
                RequestOptions::JSON => [
                    "page" => 0,
                    "pageSize" => 16,
                    "filters" => [
                        "dominio" => $rainewsArchive->attr('domain'),
                        "tematica" => [$json[0]['pipe']],
                    ],
                    "post_filters" => null,
                    "mode" => "archive",
                    "param" => null,
                ]
            ]);

            $json = json_decode($response->getBody()->getContents(), true);

            $baseUrl = 'https://www.rainews.it';
            $lastAddedPublicationTime = $this->lastPublicationTime;
            $lastAddedPublicationTime = $lastAddedPublicationTime->setTimezone($this->timezone);
            $result = [];

            foreach ($json['hits'] as $item) {
                try {
                    $title = $item['title'];
                    $pageLink = $baseUrl . $item['weblink'];
                    $publicationDate = $this->createDateFromString($item['publication_date']);
                    $hashPreview = $this->hashImageService->hashImage($baseUrl . $item['images']['default']);

                    if ($publicationDate >= $lastAddedPublicationTime) {
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
