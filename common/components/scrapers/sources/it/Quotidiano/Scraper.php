<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\it\Quotidiano;

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
 * @package common\components\scrapers\sources\it\Quotidiano;
 *
 * @Config (
 * timezone="Europe/Paris", urls={
 * "https://www.quotidiano.net/cronaca",
 * "https://www.quotidiano.net/economia",
 * "https://www.quotidiano.net/politica",
 * "https://www.quotidiano.net/esteri",
 * "https://www.quotidiano.net/sport?refresh_ce",
 * "https://www.quotidiano.net/magazine",
 * "https://www.quotidiano.net/moda",
 * "https://www.quotidiano.net/salute",
 * "https://www.quotidiano.net/itinerari",
 * "https://www.quotidiano.net/tech",
 * "https://www.quotidiano.net/roma?refresh_ce",
 * "https://www.quotidiano.net/napoli?refresh_ce",
 * "http://motori.quotidiano.net/"
 * })
 */
class Scraper extends \common\components\scrapers\common\Scraper implements ArticleBodyScraper
{

    private const DUMMY_IMAGE_URL = 'https://www.quotidiano.net/og/qn.jpg';
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
            return $this->XPathParser->parseDescription($html);
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//div[contains(@class, 'Slots_autoWidget__')]";
            $articlesNode = $html->filterXPath($selector);

            $articles = $articlesNode->filterXPath("//article[contains(@class, 'Slots_autoWidget__')]");
            $lastAddedPublicationTime = $this->lastPublicationTime;
            $lastAddedPublicationTime = $lastAddedPublicationTime->setTimezone($this->timezone);
            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    $linkNode = $node->filterXPath("//h3//a|//h4//a|//a[@class='background-image']|//a[@class='th post-thumbnail ']|//div[contains(@class, 'Card_card__image__')]//a|//a[@itemprop='url']");
                    $pageLink = $linkNode->attr('href');

                    $title = $node->filterXPath('//h3|//h4|//h6|//h5|//h3//p//a')->text();
                    if (!$title) {
                        $title = $node->filterXPath('//a[contains(@class, "Card_card__link__")][1]')->text();
                    }

                    if (!$title) {
                        continue;
                    }
                    $pageLink = str_replace(' ', '%20', $pageLink);
                    $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                    if ($this->isNeedSkipArticle($pageContent)) {
                        continue;
                    }
                    $html = new Crawler();
                    $html->addHtmlContent($pageContent->getBody()->getContents());

                    $dataArticle = $html->filterXPath(
                        "//script[@type='application/ld+json' and not(contains(@class, 'yoast-schema-graph '))]")
                        ->first();

                    if (!$dataArticle->count()) {
                        continue;
                    }

                    $dataArticle = json_decode($dataArticle->text(), true);

                    if ((!isset($dataArticle['isAccessibleForFree']) || $dataArticle['isAccessibleForFree'] == 'True') && !$html->filterXPath('//div[contains(@class, "Paywall_paywall__head__")]')->count()) {
                        if (!isset($dataArticle['datePublished'])) {
                            continue;
                        }
                        $publicationDate = $this->createDateFromString($dataArticle['datePublished']);

                        $hashPreview = null;
                        if (isset($dataArticle['image']['url']) && !empty($dataArticle['image']['url']) && $dataArticle['image']['url'] !== 'MISSING' && $dataArticle['image']['url'] !== self::DUMMY_IMAGE_URL) {
                            $hashPreview = $this->hashImageService->hashImage($dataArticle['image']['url']);
                        } else {
                            $ogImage = $html->filterXPath('//meta[@property="og:image"]')->first();
                            if ($ogImage->count()) {
                                $imageUrl = $ogImage->attr('content');
                                if ($imageUrl !== self::DUMMY_IMAGE_URL) {
                                    $hashPreview = $this->hashImageService->hashImage($ogImage->attr('content'));
                                }
                            }
                        }


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
