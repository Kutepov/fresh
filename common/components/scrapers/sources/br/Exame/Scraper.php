<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\br\Exame;

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
 * @package common\components\scrapers\sources\br\Exame;
 *
 * @Config (
 * timezone="America/Sao_Paulo", urls={
 * "https://exame.com/brasil/",
 * "https://exame.com/bussola/",
 * "https://exame.com/carreira/",
 * "https://exame.com/casual/",
 * "https://exame.com/ciencia/",
 * "https://exame.com/economia/",
 * "https://invest.exame.com/esg",
 * "https://exame.com/exame-in/",
 * "https://exame.com/inovacao/",
 * "https://exame.com/marketing/",
 * "https://exame.com/mercado-imobiliario/",
 * "https://invest.exame.com/mercados",
 * "https://exame.com/mundo/",
 * "https://exame.com/negocios/",
 * "https://exame.com/pme/",
 * "https://exame.com/tecnologia/"
 * })
 */
class Scraper extends \common\components\scrapers\common\Scraper implements ArticleBodyScraper
{

    private const MONTHS = [
        'jan' => 1,
        'fev' => 2,
        'mar' => 3,
        'abr' => 4,
        'Maio' => 5,
        'jun' => 6,
        'jul' => 7,
        'ago' => 8,
        'set' => 9,
        'out' => 10,
        'nov' => 11,
        'dez' => 12
    ];

    /**
     * @var XPathParserV2
     */
    private $XPathParser;

    /**
     * @var HashImageService
     */
    private $hashImageService;

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
            $html->addHtmlContent($response->getBody()->getContents());
            return $this->XPathParser->parseDescription($html, '//div[contains(@class, "News__SubTitle-sc")]//p[1]');
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//div[contains(@class, 'main-container')]|//div[@id='__next']";
            $articlesNode = $html->filterXPath($selector);

            $articles = $articlesNode->filterXPath("//h2//a|//h3//a");
            $lastAddedPublicationTime = $this->lastPublicationTime;
            $lastAddedPublicationTime = $lastAddedPublicationTime->setTimezone($this->timezone);
            $result = [];
            $baseUrl = 'https://exame.com';
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    $pageLink = $node->attr('href');

                    $title = $node->text();

                    $pageLink = $baseUrl . $pageLink;

                    $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                    if ($this->isNeedSkipArticle($pageContent)) {
                        continue;
                    }
                    $html = new Crawler();
                    $html->addHtmlContent($pageContent->getBody()->getContents());

                    $dataArticle = $html->filterXPath(
                        "//script[@type='application/ld+json'][2]")
                        ->first();

                    if (!$dataArticle->count()) {
                        continue;
                    }

                    $dataArticle = json_decode($dataArticle->text(), true);


                    if (!isset($dataArticle['datePublished'])) {
                        continue;
                    }
                    $publicationDate = $this->createDateFromString($dataArticle['datePublished']);

                    $hashImage = $this->hashImageService->hashImage($dataArticle['image']);

                    if ($publicationDate > $lastAddedPublicationTime) {
                        $result[] = new ArticleItem($pageLink, $title, $publicationDate, $hashImage);
                    }
                } catch (\Throwable $exception) {
                    $this->logArticleItemException($exception, $pageLink);
                }
            }
            yield $result;
        });
    }

    private function prepareDateString(string $string): string
    {
        $string = str_replace('h', ':', $string);
        [$date, $time] = mb_split(',', $string);
        [$day, $month, $year] = mb_split( ' ', $date);
        $month = self::MONTHS[$month];
        return $day.'-'.$month.'-'.$year.$time;
    }
}
