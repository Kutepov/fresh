<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\it\Fanpage;

use common\components\guzzle\Guzzle;
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
 * @package common\components\scrapers\sources\it\Fanpage;
 *
 * @Config (
 * timezone="Europe/Paris", urls={
 * "https://www.fanpage.it/attualita/",
 * "https://tech.fanpage.it/",
 * "https://music.fanpage.it/",
 * "https://www.fanpage.it/sport/calcio/",
 * "https://www.fanpage.it/sport/motori/",
 * "https://scienze.fanpage.it/",
 * "https://www.fanpage.it/cultura/",
 * "https://www.fanpage.it/politica/",
 * "https://spettacolo.fanpage.it/",
 * "https://donna.fanpage.it/",
 * "https://design.fanpage.it/",
 * "https://www.fanpage.it/diritto/",
 * "https://www.fanpage.it/esteri/",
 * "https://www.fanpage.it/sport/"
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
            return $this->XPathParser->parseDescription($html, '//h1[@class="fp_intro__title"]');
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');
            $articles = $html->filterXPath("//div[contains(@class, 'fp_article-card-image')]");

            $lastAddedPublicationTime = $this->lastPublicationTime;
            $lastAddedPublicationTime = $lastAddedPublicationTime->setTimezone($this->timezone);
            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    $linkNode = $node->attr('class') === 'classh2 title-storyBox' ?
                        $node->filterXPath("//a")->first() :
                        $node->filterXPath("//a[@class='fp_article-card-image__title']|
                                                  //a[@class='fp_article-card-image__content-wrap']|
                                                  //a[@class='fp_article-card']")->first();
                    if (!$linkNode->count()) {
                        continue;
                    }
                    $pageLink = $linkNode->attr('href');

                    if (($cardTitle = $linkNode->filter('.fp_article-card__title')) && $cardTitle->count()) {
                        $title = $cardTitle->text();
                    }
                    else {
                        $title = $linkNode->text();
                    }

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

                    if (!isset($dataArticle['datePublished'])) {
                        continue;
                    }
                    $publicationDate = $this->createDateFromString($dataArticle['datePublished']);

                    if (isset($dataArticle['thumbnail']['url'])) {
                        $imgUrl = $dataArticle['thumbnail']['url'];
                    }
                    else {
                        $imgUrl = isset($dataArticle['image']['url']) ? $dataArticle['image']['url'] : $dataArticle['image'][0]['url'];
                    }

                    $hashPreview = $this->hashImageService->hashImage($imgUrl);

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

    public function proxyEnablingAttempt(): ?int
    {
        return Guzzle::PROXY_ALWAYS_ENABLED;
    }
}
