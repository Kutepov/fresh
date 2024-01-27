<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\by\BeltaBy;

use common\components\scrapers\common\ArticleBodyScraper;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\NewsCutter;
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

/**
 * Class Scraper
 * @package common\components\scrapers\sources\by\BeltaBy
 *
 * @Config (
 * timezone="Europe/Minsk", urls={
 * "https://www.belta.by/president/",
 * "https://www.belta.by/politics/",
 * "https://www.belta.by/economics/",
 * "https://www.belta.by/society/",
 * "https://www.belta.by/regions/",
 * "https://www.belta.by/incident/",
 * "https://www.belta.by/tech/",
 * "https://www.belta.by/world/",
 * "https://www.belta.by/culture/",
 * "https://www.belta.by/sport/",
 * "https://www.belta.by/events/",
 * "https://www.belta.by/kaleidoscope/"
 * })
 */
class Scraper extends \common\components\scrapers\common\Scraper implements ArticleBodyScraper
{
    private const OVERRIDE_REPLACE_TAGS = [];

    /**
     * @var SelectorsRemover
     */
    private $selectorsRemover;

    /**
     * @var XPathParserV2
     */
    private $XPathParser;

    /**
     * @var NewsCutter
     */
    private $newsCutter;

    /**
     * @var BaseUrls
     */
    private $BaseUrls;

    public function __construct(
        SelectorsRemover $selectorsRemover,
        XPathParserV2 $XPathParserV2,
        NewsCutter $newsCutter,
        BaseUrls $BaseUrls,
        $config = []
    )
    {
        $this->selectorsRemover = $selectorsRemover;
        $this->XPathParser = $XPathParserV2;
        $this->newsCutter = $newsCutter;
        $this->BaseUrls = $BaseUrls;

        parent::__construct($config);
    }


    public function parseArticleBody(string $url): PromiseInterface
    {
        return $this->sendAsyncRequestWithProxy(new Request('GET', $url))->then(function (ResponseInterface $response) {

            $raplaceTags = self::OVERRIDE_REPLACE_TAGS;

            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//div[contains(@class, 'inner_content')]";

            $textNode = $html->filterXPath($selector)->first();

            $this->selectorsRemover->remove(
                "
            //script|
            //div[@class='invite_in_messagers']|
            //div[@class='news_tags_block']|
            //div[contains(@class, 'fp_block')]|
            //a[@rel = 'nofollow']|
            //a[@title='Добавить БЕЛТА в избранное']|
            //div[contains(@class, 'news_accent_block')]|
            //div[contains(@class, 'js-mediator-article')]//blockquote[contains(@class, 'twitter-tweet')]//text()|
            ",
                $textNode
            );

            $newsLinks = $textNode->filterXPath("//div[contains(@class, 'js-mediator-article')]//a");
            $this->newsCutter->cutterByLinks($newsLinks);

            $text = $textNode->filterXPath(
                "
            //div[contains(@class, 'text')]//img|
            //div[contains(@class, 'js-mediator-article')]//p[not(ancestor::blockquote)]|
            //div[contains(@class, 'js-mediator-article')]//p[not(ancestor::blockquote)]|
            //div[contains(@class, 'js-mediator-article')]//ul|
            //div[contains(@class, 'js-mediator-article')]//ol|
            //div[contains(@class, 'js-mediator-article')]//div|
            //div[contains(@class, 'js-mediator-article')]//i|
            //div[contains(@class, 'js-mediator-article')]//b|
            //div[contains(@class, 'js-mediator-article')]//text()|
            //div[contains(@class, 'js-mediator-article')]//blockquote[contains(@class, 'instagram-media')]|
            //div[contains(@class, 'js-mediator-article')]//blockquote[contains(@class, 'twitter-tweet')]/a|
            //div[contains(@class, 'js-mediator-article')]//iframe|
            //script[@data-telegram-post]|
            "
            );

            $this->BaseUrls->addImageUrl('https://www.belta.by');

            $result = $this->XPathParser->parse($text, $raplaceTags, $this->BaseUrls);


            return $result;
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//div[@id='inner']";
            $articlesNode = $html->filterXPath($selector);

            $articles = $articlesNode->filterXPath("//div[@class='news_item']");

            $baseUrl = 'https://www.belta.by';

            $lastAddedPublicationTime = $this->lastPublicationTime;

            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    $linkNode = $node->filterXPath('//a')->first();
                    $pageLink = $baseUrl.$linkNode->attr('href');
                    $title = $linkNode->text();


                    $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                    if ($this->isNeedSkipArticle($pageContent)) {
                        continue;
                    }
                    $html = new Crawler();
                    $html->addHtmlContent($pageContent->getBody()->getContents(), 'UTF-8');

                    $articlePubDate = $html->filterXPath("//head//meta[@property='article:published_time']")->first();
                    $pubDateAttr = $articlePubDate->attr('content');
                    if (!$pubDateAttr) {
                        continue;
                    }
                    $publicationDate = $this->createDateFromString($pubDateAttr);

                    if ($publicationDate > $lastAddedPublicationTime) {
                        $result[] = new ArticleItem($pageLink, $title, $publicationDate);
                    }
                } catch (\Throwable $exception) {
                }

            }

            yield $result;
        });
    }
}
