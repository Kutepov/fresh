<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\ru\Lenta;

use common\components\scrapers\common\ArticleBodyScraper;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\XPathParserV2;
use common\components\scrapers\common\Config;
use common\components\scrapers\common\symfony\SiteParserModule\Infrastructure\SelectorsRemover;
use common\components\scrapers\dto\ArticleItem;
use GuzzleHttp\Promise\Coroutine;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DomCrawler\Crawler;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\NewsCutter;

/**
 * Class Scraper
 * @package common\components\scrapers\sources\ru\Lenta
 *
 * @Config (
 * timezone="Europe/Moscow", urls={
 * "https://lenta.ru/rubrics/russia/",
 * "https://lenta.ru/rubrics/world/",
 * "https://lenta.ru/rubrics/ussr/",
 * "https://lenta.ru/rubrics/economics/",
 * "https://lenta.ru/rubrics/forces/",
 * "https://lenta.ru/rubrics/science/",
 * "https://lenta.ru/rubrics/sport/",
 * "https://lenta.ru/rubrics/culture/",
 * "https://lenta.ru/rubrics/media/",
 * "https://lenta.ru/rubrics/style/",
 * "https://lenta.ru/rubrics/travel/",
 * "https://lenta.ru/rubrics/life/",
 * "https://lenta.ru/rubrics/realty/"
 * })
 */
class Scraper extends \common\components\scrapers\common\Scraper implements ArticleBodyScraper
{

    /**
     * @var NewsCutter
     */
    private $newsCutter;

    /**
     * @var XPathParserV2
     */
    private $XPathParser;

    /**
     * @var SelectorsRemover
     */
    private $selectorRemover;

    public function __construct(
        NewsCutter $newsCutter,
        XPathParserV2 $XPathParser,
        SelectorsRemover $selectorsRemover,
        $config = []
    )
    {
        $this->newsCutter = $newsCutter;
        $this->XPathParser = $XPathParser;
        $this->selectorRemover = $selectorsRemover;

        parent::__construct($config);
    }

    public function parseArticleBody(string $url): PromiseInterface
    {
        return $this->sendAsyncRequestWithProxy(new Request('GET', $url))->then(function (ResponseInterface $response) {
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//div[contains(@class, 'topic-page__container')]";

            $textNode = $html->filterXPath($selector);

            $this->selectorRemover->remove('//div[contains(@class, "js-box-inline-topic")]', $textNode);

            $newsLinks = $textNode->filterXPath("//a");
            $this->newsCutter->cutterByLinks($newsLinks);

            $text = $textNode->filterXPath(
                "
                //h1[@class='topic-header__titles']|
            //div[contains(@class, 'topic-page__body')]//img|
            //div[contains(@class, 'topic-page__body')]//p|
            //div[contains(@class, 'topic-page__body')]//ul|
            //div[contains(@class, 'topic-page__body')]//ol|
            //div[contains(@class, 'topic-page__body')]//h1|
            //div[contains(@class, 'topic-page__body')]//div[contains(@class, 'note__text')]|
            //div[contains(@class, 'topic-page__body')]//blockquote|
           
            "
            );

            $imageNodes = $textNode->filterXPath("//div[contains(@class, 'b-topic__title-image')]//img");
            $isNeedPrviewImg = !$imageNodes->count();

            $result = $this->XPathParser->parse($text, null, null, $isNeedPrviewImg);

            return $result;
        });
    }


    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));

            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents());

            $selector = "//main[contains(@class, 'layout__content js-site-container')]";
            $articlesNode = $html->filterXPath($selector);

            $this->selectorRemover->remove('//a[@target="_blank"]', $articlesNode);

            $articles = $articlesNode->filterXPath("//a[contains(@class, '_longgrid')]");

            $lastAddedPublicationTime = $this->lastPublicationTime;
            $basePath = 'https://lenta.ru';
            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    $pageLink = $basePath . $node->attr('href');

                    if (!filter_var($pageLink, FILTER_VALIDATE_URL)) {
                        continue;
                    }

                    $this->selectorRemover->remove("//time", $node);
                    $title = $node->filterXPath("//h3|//h1|//span[@class='card-mini__title']")->text();

                    $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                    if ($this->isNeedSkipArticle($pageContent)) {
                        continue;
                    }
                    $html = new Crawler();
                    $html->addHtmlContent($pageContent->getBody()->getContents(), 'UTF-8');

                    $dataArticle = $html->filterXPath(
                        "//script[@type='application/ld+json']")
                        ->last();

                    if (!$dataArticle->count()) {
                        continue;
                    }

                    $dataArticle = json_decode($dataArticle->text(), true);

                    if (!isset($dataArticle['datePublished'])) {
                        continue;
                    }

                    $pubDateAttr = $dataArticle['datePublished'];

                    $publicationDate = $this->createDateFromString($pubDateAttr);

                    if ($publicationDate > $lastAddedPublicationTime) {
                        $result[] = new ArticleItem($pageLink, $title, $publicationDate);
                    }
                } catch (\Throwable $exception) {
                    $this->logArticleItemException($exception, $pageLink);
                }
            }


            yield $result;
        });
    }
}
