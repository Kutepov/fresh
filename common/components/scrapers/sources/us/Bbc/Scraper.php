<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\us\Bbc;

use common\components\guzzle\Guzzle;
use common\components\scrapers\common\ArticleBodyScraper;
use common\components\scrapers\common\helpers\PreviewHelper;
use common\components\scrapers\common\services\HashImageService;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\XPathParserV2;
use common\components\scrapers\dto\ArticleItem;
use GuzzleHttp\Promise\Coroutine;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DomCrawler\Crawler;
use common\components\scrapers\common\Config;

/**
 * Class Scraper
 * @package common\components\scrapers\sources\us\Bbc
 *
 * @Config (timezone="America/New_York", urls={
 * "https://www.bbc.com/culture/",
 * "https://www.bbc.com/news/health",
 * "https://www.bbc.com/news/science_and_environment",
 * "https://www.bbc.com/news/technology",
 * "https://www.bbc.com/news/business",
 * "https://www.bbc.com/news/uk",
 * "https://www.bbc.com/news/world",
 * "https://www.bbc.com/sport",
 * })
 */
class Scraper extends \common\components\scrapers\common\Scraper implements ArticleBodyScraper
{
    private const MONTHS = [
        'January' => 1,
        'February' => 2,
        'March' => 3,
        'April' => 4,
        'May' => 5,
        'June' => 6,
        'July' => 7,
        'August' => 8,
        'September' => 9,
        'October' => 10,
        'November' => 11,
        'December' => 12
    ];

    /**
     * @var HashImageService
     */
    private $hashImageService;

    /**
     * @var PreviewHelper
     */
    private $previewHelper;

    /**
     * @var XPathParserV2
     */
    private $XPathParser;

    public function __construct(
        PreviewHelper    $previewHelper,
        HashImageService $hashImageService,
        XPathParserV2    $XPathParser,
                         $config = []
    )
    {
        $this->previewHelper = $previewHelper;
        $this->hashImageService = $hashImageService;
        $this->XPathParser = $XPathParser;
        parent::__construct($config);
    }

    public function parseArticleBody(string $url): PromiseInterface
    {
        return $this->sendAsyncRequestWithProxy(new Request('GET', $url))->then(function (ResponseInterface $response) {
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents());
            return $this->XPathParser->parseDescription($html, '//div[@class="article__body-content"]//div[contains(@class, "article__intro")]');
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler($pageContent->getBody()->getContents());

            $selector = "//div[@id='orb-modules']|//div[@class='vertical-index']|//div[@id='root']";
            $articlesNode = $html->filterXPath($selector)->first();

            $articles = $articlesNode->filterXPath("
                //div[@class='gel-layout__item gs-u-pb+@m gel-1/1 gel-1/1@xl gel-2/5@xxl gs-u-ml0 nw-o-keyline nw-o-no-keyline@m']|
                //div[@class='gel-layout__item gel-1/1 gel-3/5@xxl gs-u-display-none@xl gs-u-display-block@xxl']//div[@class='gs-c-promo gs-t-News nw-c-promo gs-o-faux-block-link gs-u-pb gs-u-pb+@m nw-p-default gs-c-promo--inline gs-c-promo--stacked@m gs-c-promo--flex']|
                //div[@class='nw-c-5-slice gel-layout gel-layout--equal b-pw-1280']//div[@class='gs-c-promo gs-t-News nw-c-promo gs-o-faux-block-link gs-u-pb gs-u-pb+@m nw-p-default gs-c-promo--inline gs-c-promo--stacked@m gs-c-promo--flex']|
                //div[@class='nw-c-5-slice gel-layout gel-layout--equal b-pw-1280']//div[@class='gs-c-promo gs-t-News nw-c-promo gs-o-faux-block-link gs-u-pb gs-u-pb+@m nw-p-default gs-c-promo--stacked gs-c-promo--flex']|
                //ol[@class='gs-u-m0 gs-u-p0 lx-stream__feed qa-stream']//article|
                //div[@class='article-hero b-reith-sans-font b-font-weight-300 article-hero--gradient article-hero--small-mobile article-hero--small-tablet article-hero--desktop article-hero--large-desktop']|
                //div[@class='rectangle-story-group__article-hero rectangle-story-group__article-hero--tablet']|
                //div[@class='rectangle-story-item b-reith-sans-font rectangle-story-item--tablet']|
                //div[@class='swimlane__item swimlane__item--desktop swimlane__item--four-columns']|
                //li[contains(@class, '-ListItem ')] 
            ");

            $lastAddedPublicationTime = $this->lastPublicationTime;
            $baseUrl = 'https://www.bbc.com';
            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {

                    if (stripos($url, '/culture') === false) {
                        $linkNode = $node->filterXPath("//a[not(contains(@href, '/tags'))]")->first();
                    } else {
                        $linkNode = $node->filterXPath("//a[contains(@href, '/article')]")->first();
                    }

                    if ($linkNode->count() === 0) {
                        continue;
                    }

                    $pageLink = $linkNode->attr('href');

                    if (!filter_var($pageLink, FILTER_VALIDATE_URL)) {
                        $pageLink = $baseUrl . $pageLink;
                    }

                    $title = $node->filterXPath("//h3|//div[@class='rectangle-story-item__container']//a[@class='rectangle-story-item__title']|//h2|//div[@class='article-hero__title-text']|//p[contains(@class, '-PromoHeadline ')]")->text();

                    $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                    if ($this->isNeedSkipArticle($pageContent)) {
                        continue;
                    }
                    $html = new Crawler($pageContent->getBody()->getContents());

                    if (stripos($url, '/culture') === false) {


                        $dataArticle = $html->filterXPath("//script[@type='application/ld+json']")->first();

                        if (!$dataArticle->count()) {

                            $dateNode = $html->filterXPath('//time')->first();

                            if (!$dateNode->count()) {
                                continue;
                            }

                            $dateString = $dateNode->attr('datetime');
                            $publicationDate = $this->createDateFromString($dateString);
                            $hashPreview = $this->previewHelper->getOgImageUrlHash($html);

                        } else {
                            $dataArticle = json_decode($dataArticle->text(), true);

                            if (!isset($dataArticle['datePublished'])) {
                                continue;
                            }

                            $publicationDate = $this->createDateFromString($dataArticle['datePublished']);
                            $imgUrl = '';
                            if (isset($dataArticle['thumbnailUrl'])) {
                                $imgUrl = $dataArticle['thumbnailUrl'];
                            } elseif (isset($dataArticle['image'])) {
                                if (is_array($dataArticle['image'])) {
                                    $imgUrl = $dataArticle['image']['url'];
                                } else {
                                    $imgUrl = $dataArticle['image'];
                                }
                            }
                            $hashPreview = null;
                            if ($imgUrl !== '') {
                                $hashPreview = $this->hashImageService->hashImage($imgUrl);
                            }
                        }


                    } else {
                        $dateNode = $html->filterXPath("//span[@class='b-font-family-serif b-font-weight-300']")->first();
                        if (!$dateNode->count()) {
                            continue;
                        }

                        $dateString = $dateNode->text();
                        $publicationDate = $this->prepareDate($dateString);
                        $hashPreview = $this->previewHelper->getOgImageUrlHash($html);
                    }

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

    private function prepareDate($dateString)
    {
        [$day, $month, $year] = explode(' ', $dateString);
        $month = self::MONTHS[$month];
        $day = substr($day, 0, -2);
        return $this->createDateFromString($day . '-' . $month . '-' . $year);
    }

    public function proxyEnablingAttempt(): ?int
    {
        return Guzzle::PROXY_ALWAYS_ENABLED;
    }
}