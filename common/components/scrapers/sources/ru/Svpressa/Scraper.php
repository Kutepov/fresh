<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\ru\Svpressa;

use Carbon\Carbon;
use common\components\scrapers\common\ArticleBodyScraper;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\BaseUrls;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\XPathParserV2;
use common\components\scrapers\common\symfony\SiteParserModule\Infrastructure\SelectorsRemover;
use common\components\scrapers\common\services\HashImageService;
use common\components\scrapers\common\Config;
use common\components\scrapers\dto\ArticleBodyNode;
use common\components\scrapers\dto\ArticleItem;
use GuzzleHttp\Promise\Coroutine;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DomCrawler\Crawler;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\NewsCutter;

/**
 * Class Scraper
 * @package common\components\scrapers\sources\ru\Svpressa
 *
 * @Config (
 * timezone="Europe/Moscow", urls={
 * "https://svpressa.ru/accidents/news/",
 * "https://svpressa.ru/auto/news/",
 * "https://svpressa.ru/business/news/",
 * "https://svpressa.ru/culture/news/",
 * "https://svpressa.ru/economy/news/",
 * "https://svpressa.ru/health/news/",
 * "https://svpressa.ru/politic/news/",
 * "https://svpressa.ru/society/news/",
 * "https://svpressa.ru/sport/news/",
 * "https://svpressa.ru/war21/news/",
 * "https://svpressa.ru/world/news/"
 * })
 */
class Scraper extends \common\components\scrapers\common\Scraper implements ArticleBodyScraper
{

    private const MONTH = [
        'января' => '01',
        'февраля' => '02',
        'марта' => '03',
        'апреля' => '04',
        'мая' => '05',
        'июня' => '06',
        'июля' => '07',
        'августа' => '08',
        'сентября' => '09',
        'октября' => '10',
        'ноября' => '11',
        'декабря' => '12',
    ];

    /**
     * @var NewsCutter
     */
    private $newsCutter;

    /**
     * @var HashImageService
     */
    private $hashImageService;

    /**
     * @var XPathParserV2
     */
    private $XPathParser;

    /**
     * @var BaseUrls
     */
    private $baseUrls;

    /**
     * @var SelectorsRemover
     */
    private $selectorsRemover;

    public function __construct(
        HashImageService $hashImageService,
        NewsCutter $newsCutter,
        SelectorsRemover $selectorsRemover,
        XPathParserV2 $XPathParser,
        BaseUrls $baseUrls,
        $config = []
    )
    {
        $this->hashImageService = $hashImageService;
        $this->selectorsRemover = $selectorsRemover;
        $this->newsCutter = $newsCutter;
        $this->XPathParser = $XPathParser;
        $this->baseUrls = $baseUrls;

        parent::__construct($config);
    }

    public function parseArticleBody(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $isTopicLink = strpos($html->filterXPath("//head//link[@rel='canonical']")->first()->attr('href'), '/topics');

            $selector = $isTopicLink ? "//div[contains(@class, 'b-content__main')]" : "//article[@class='b-text']";

            $textNode = $html->filterXPath($selector);

            $this->selectorsRemover->remove(
                "
            //div[contains(@class, 'b-info_tags__container')]|
            //div[contains(@class, 'b-blockquote-news')]|
            //p[contains(text(), 'Подписывайтесь на')]|
            //p[contains(text(), 'Подписывайтесь на')]/following::p[contains(text(), 'Прямая ')]|
            //div[contains(@class, 'b-text__block_img')][div[contains(@class, 'b-content__title_text') and contains(text(), 'Фотогалерея')]]|
            //meta[contains(@content, 'p-')]
            ",
                $textNode
            );

            $newsLinks = $textNode->filterXPath("//div[contains(@class, 'b-text__content')]//a");
            $this->newsCutter->cutterByLinks($newsLinks);

            $text = $textNode->filterXPath(
                "
            //div[contains(@class, 'b-text__header')]//img|
            //div[contains(@class, 'b-text__content')]//div[contains(@class, 'b-text__block')]//p|
            //div[contains(@class, 'b-text__content')]//div[contains(@class, 'b-text__img')]//img|
            //div[contains(@class, 'b-text__content')]//div[contains(@class, 'b-text__video')]//iframe|
            //div[contains(@class, 'b-text__content')]//div[contains(@class, 'b-text__block')]//ul|
            //div[contains(@class, 'b-text__content')]//div[contains(@class, 'b-text__block')]//ol|
            //div[contains(@class, 'b-text__content')]//div[contains(@class, 'b-text__block')]//table[contains(@class, 'b-tabl')]
            "
            );

            if ($isTopicLink) {
                $text = $textNode->filterXPath(
                    "
                //div[contains(@class, 'b-text__header')]//img|
                //div[contains(@class, 'b-text__block')]//p|
                //div[contains(@class, 'b-text__img')]//img|
                //div[contains(@class, 'b-text__video')]//iframe|
                //div[contains(@class, 'b-text__block')]//ul|
                //div[contains(@class, 'b-text__block')]//ol|
                //div[contains(@class, 'b-text__block')]//table[contains(@class, 'b-table')]
                "
                );
            }

            $imageNodes = $textNode->filterXPath("//div[contains(@class, 'b-text__content')]//div[contains(@class, 'b-text__img')]//img");
            $isNeedPrviewImg = !$imageNodes->count();
            $imageBaseUrl = 'https://'.$this->getHost().'/';
            $this->baseUrls->addImageUrl($imageBaseUrl);
            $result = $this->XPathParser->parse($text, null, $this->baseUrls, $isNeedPrviewImg);

            yield $result;
        });
    }

    public function getHost(): string
    {
        return 'svpressa.ru';
    }


    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));

            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//div[contains(@class, 'b-content__main')]";
            $articlesNode = $html->filterXPath($selector)->first();

            $articles = $articlesNode->filterXPath('//article[contains(@class, "b-article_item")]');

            $lastAddedPublicationTime = $this->lastPublicationTime;

            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    $articlePubDate = $node->filterXPath('//div[contains(@class, "b-article__date")]');
                    $pubDateAttr = $articlePubDate->text();
                    if (!$pubDateAttr) {
                        continue;
                    }
                    $publicationDate = $this->prepareTime($pubDateAttr);

                    if ($publicationDate > $lastAddedPublicationTime) {
                        $title = $node->filterXPath('//a[contains(@class, "b-article__title")]')->text();
                        $articleLink = 'https://svpressa.ru'.$node->filterXPath('//a[contains(@class, "b-article__title")]')->attr('href');


                        $result[] = new ArticleItem($articleLink, $title, $publicationDate);
                    }
                } catch (\Throwable $exception) {
                    $this->logArticleItemException($exception, $articleLink);
                }
            }


            yield $result;
        });
    }

    private function prepareTime(string $rawDatetime): Carbon
    {
        $arrayData = mb_split(' ', $rawDatetime);

        if (3 === count($arrayData)) {
            [$day, $month, $time] = $arrayData;
            $year = getdate()['year'];
        } else {
            [$day, $month, $year, $time] = $arrayData;
        }
        [$hour, $minute] = mb_split(':', $time);

        $articleDate = new \DateTime();
        $month = self::MONTH[$month];
        $articleDate->setDate((int) $year, (int) $month, (int) $day);
        $articleDate->setTime((int) $hour, (int) $minute);

        return $this->createDateFromString($articleDate->format('Y-m-d H:i:s'));
    }

}
