<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\pl\Tvp;

use Carbon\Carbon;
use common\components\scrapers\common\ArticleBodyScraper;
use common\components\scrapers\common\services\HashImageService;
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
 * @package common\components\scrapers\sources\pl\Tvp
 *
 * @Config (
 * timezone="Europe/Warsaw", urls={
 * "https://www.tvp.info/191866/polska",
 * "https://www.tvp.info/191867/swiat",
 * "https://www.tvp.info/40288541/opinie",
 * "https://www.tvp.info/191868/biznes",
 * "https://www.tvp.info/191871/spoleczenstwo",
 * "https://www.tvp.info/191869/kultura",
 * "https://www.tvp.info/191870/nauka",
 * "https://www.tvp.info/191872/rozmaitosci",
 * "https://www.tvp.info/41327367/polska-to-wiecej",
 * "https://www.tvp.info/37626733/mundial-2018"
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
        HashImageService $hashImageService,
        XPathParserV2 $XPathParser,
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
            return $this->XPathParser->parseDescription($html, '//p[@class="am-article__heading article__width"][1]');
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//script[@type='text/javascript']";
            $articlesNode = $html->filterXPath($selector);

            $pageJson = substr(stristr($articlesNode->eq(7)->text(), 'directoryData = '), 16, -1);

            $pageJson = json_decode($pageJson);

            $lastAddedPublicationTime = $this->lastPublicationTime;

            $result = [];
            $mainUrl = "https://www.tvp.info";
            foreach ($pageJson->items as $item) {
                try {
                    $pageLink = $mainUrl . $item->url;
                    $title = $item->title;

                    $publicationDate = Carbon::createFromTimestampMs($item->publication_start, $this->timezone);
                    $publicationDate->setTimezone($this->timezone);
                    $hashPreview = $this->hashImageService->hashImage($item->image->url);

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
