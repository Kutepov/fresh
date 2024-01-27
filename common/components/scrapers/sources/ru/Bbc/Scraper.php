<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\ru\Bbc;

use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\BaseUrls;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\XPathParserV2;
use common\components\scrapers\common\symfony\SiteParserModule\Infrastructure\SelectorsRemover;
use common\components\scrapers\common\Config;
use common\components\scrapers\dto\ArticleItem;
use GuzzleHttp\Promise\Coroutine;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DomCrawler\Crawler;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\NewsCutter;
use common\components\scrapers\common\ArticleBodyScraper;

/**
 * Class Scraper
 * @package common\components\scrapers\sources\ru\Bbc
 *
 * @Config (
 * timezone="Europe/Moscow", urls={
 * "https://www.bbc.com/russian/topics/cez0n29ggrdt"
 * })
 */
class Scraper extends \common\components\scrapers\common\Scraper implements ArticleBodyScraper
{
    private const REPLACE_TAGS = [
        'iframe' => [
            [
                'contains' => 'youtube.com',
                'attribute' => 'src',
                'valueType' => 'video',
                'elementName' => 'video',
            ],
            [
                'contains' => 'player.vimeo.com',
                'attribute' => 'src',
                'valueType' => 'video',
                'elementName' => 'video',
            ],
            [
                'contains' => 'instagram.com',
                'attribute' => 'src',
                'valueType' => 'video',
                'elementName' => 'video',
            ],
            [
                'contains' => 'facebook.com',
                'attribute' => 'src',
                'valueType' => 'video',
                'elementName' => 'facebook',
            ],
            [
                'contains' => 't.me',
                'attribute' => 'src',
                'valueType' => 'src',
                'elementName' => 'telegram',
            ],
            [
                'contains' => 'vk.com',
                'attribute' => 'src',
                'valueType' => 'video',
                'elementName' => 'video',
            ],
            [
                'contains' => 'soundcloud',
                'attribute' => 'src',
                'valueType' => 'webview',
                'elementName' => 'soundcloud',
            ],
            [
                'contains' => '/ws/av-embeds',
                'attribute' => 'src',
                'valueType' => 'video',
                'elementName' => 'video',
            ],
        ],
    ];


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
    private $selectorsRemover;

    /**
     * @var BaseUrls
     */
    private $BaseUrls;

    public function __construct(
        NewsCutter $newsCutter,
        SelectorsRemover $selectorsRemover,
        XPathParserV2 $XPathParser,
        BaseUrls $BaseUrls,
        $config = []
    )
    {
        $this->selectorsRemover = $selectorsRemover;
        $this->newsCutter = $newsCutter;
        $this->XPathParser = $XPathParser;
        $this->BaseUrls = $BaseUrls;

        parent::__construct($config);
    }

    public function parseArticleBody(string $url): PromiseInterface
    {
        return $this->sendAsyncRequestWithProxy(new Request('GET', $url))->then(function (ResponseInterface $response) {
            $html = new Crawler($response->getBody()->getContents());

            $selector = "//main[@role='main']";

            $textNode = $html->filterXPath($selector);

            $this->selectorsRemover->remove(
                "
            //h1|
            //time|
            //section[@aria-label='Уведомление']|
            //li[contains(text(), 'Русская служба Би-би-си')]//ancestor::ul|
            //div[@dir='ltr'][3]|
            //section[@aria-labelledby='podcast-promo']|
            //span[contains(text(), 'Автор фото')]/parent::p|
            //img[contains(@src, 'russian_invasion_down-2x-nc')]|
            //img[@alt='Плашка']|
            //li[contains(@class, 'bbc-acwcvw')]//ancestor::ul|
            //p[contains(text(), 'Чтобы продолжать получать новости Би-би-си')]|
            //p[contains(text(), 'Загрузите наше приложение')]|
            //a[@href='https://t.me/bbcrussian']//ancestor::ul|
            //a[@href='https://play.google.com/store/apps/details?id=uk.co.bbc.russian']//ancestor::ul|
            //ul[@class='lx-share-tools__items lx-share-tools__items--align-left qa-share-tools']|
            ",
                $textNode
            );

            $newsLinks = $textNode->filterXPath('//a');
            $this->newsCutter->cutterByLinks($newsLinks);

            $text = $textNode->filterXPath(
                "
            //img|
            //p|
            //ul|
            //ol|
            //iframe|
            //blockquote[contains(@class, 'instagram-media')]|
            //blockquote[contains(@class, 'twitter-tweet')]//a|
            //script[contains(@src, 'telegram')]|
            //h2|
            //h3|
            //div[contains(@data-e2e, 'instagram-embed-https')]|
            //div[contains(@data-e2e, 'twitter-embed-https')]|
            //div[contains(@data-e2e, 'youtube-embed-https')]|
            "
            );

            $this->BaseUrls->addVideoUrl('www.bbc.com/');
            $BaseUrls = $this->BaseUrls;

            $result = $this->XPathParser->parse($text, self::REPLACE_TAGS, $BaseUrls);

            return $result;
        });
    }


    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));

            $html = new Crawler($response->getBody()->getContents());

            $selector = "//ul[@data-testid='topic-promos']";
            $articlesNode = $html->filterXPath($selector);

            $articles = $articlesNode->filterXPath("//li");

            $lastAddedPublicationTime = $this->lastPublicationTime;
            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {

                    $linkNode = $node->filterXPath('//h2//a');

                    $title = $linkNode->text();
                    $pageLink = $linkNode->attr('href');

                    $date = $node->filterXPath('//time')->attr('datetime');

                    $publicationDate = $this->createDateFromString($date);

                    if ($publicationDate > $lastAddedPublicationTime) {
                        $immutableDate = \DateTimeImmutable::createFromMutable($publicationDate);
                        $result[] = new ArticleItem($pageLink, $title, $immutableDate);
                    }
                } catch (\Throwable $exception) {
                    $this->logArticleItemException($exception, $pageLink);
                }
            }


            yield $result;
        });
    }
}
