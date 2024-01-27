<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\by\Belaruspartisan;

use common\components\scrapers\common\ArticleBodyScraper;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\NewsCutter;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\BaseUrls;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\XPathParserV2;
use common\components\scrapers\common\symfony\SiteParserModule\Infrastructure\SelectorsRemover;
use common\components\scrapers\common\Config;
use common\components\scrapers\dto\ArticleBodyNode;
use common\components\scrapers\dto\ArticleItem;
use GuzzleHttp\Promise\Coroutine;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Class Scraper
 * @package common\components\scrapers\sources\by\Belaruspartisan
 *
 * @Config (
 * timezone="Europe/Minsk", urls={
 * "https://belaruspartisan.by/economic/",
 * "https://belaruspartisan.by/interview/",
 * "https://belaruspartisan.by/life/",
 * "https://belaruspartisan.by/politic/",
 * "https://belaruspartisan.by/sport/"
 * })
 */
class Scraper extends \common\components\scrapers\common\Scraper implements ArticleBodyScraper
{
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

            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//div[contains(@class, 'pw article')]";

            $textNode = $html->filterXPath($selector)->first();

            $this->selectorsRemover->remove(
                "
            //div[contains(@class, '_not_empty_block')]|
            //a[@rel = 'nofollow']|
            //div[@class='copyright_news']|
            //div[@class='date_block']|
            //h1[@itemprop='name']
            ",
                $textNode
            );

            $newsLinks = $textNode->filterXPath("//a");
            $this->newsCutter->cutterByLinks($newsLinks);

            $text = $textNode->filterXPath(
                "
            //h1|
            //span[@itemprop='articleBody']//div|
            //img|
            //div[@class='detail_text']//b|
            //span[@itemprop='articleBody']//p[not(ancestor::blockquote)]|
            //span[@itemprop='articleBody']//p[not(ancestor::blockquote)]|
            //span[@itemprop='articleBody']//ul|
            //span[@itemprop='articleBody']//ol|
            //span[@itemprop='articleBody']//blockquote[contains(@class, 'instagram-media')]|
            //span[@itemprop='articleBody']//blockquote[contains(@class, 'twitter-tweet')]/a|
            //span[@itemprop='articleBody']//iframe|
            "
            );

            $this->BaseUrls->addImageUrl('https://belaruspartisan.by');

            $result = $this->XPathParser->parse($text, null, $this->BaseUrls);

            if (count($result->getNodes()) == 1 && $result->getNodes()[0]->getElementName() === 'image') {
                $result->add(new ArticleBodyNode('paragraph', $textNode->filterXPath('//span[@itemprop="articleBody"]')->first()->text()));
            }

            return $result;
        });

    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//div[@id='content']//div[@class = 'left_newslist']//div[@class='news_inner_list']";
            $articlesNode = $html->filterXPath($selector);

            $articles = $articlesNode->filterXPath("//div[contains(@class, 'item')]");

            $baseUrl = 'https://belaruspartisan.by';

            $lastAddedPublicationTime = $this->lastPublicationTime;
            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    $linkNode = $node->filterXPath("//div[contains(@class, 'news_name')]//a");
                    $pageLink = $baseUrl . $linkNode->attr('href');
                    $title = $linkNode->text();

                    $articlePubDate = $node->filterXPath("//span[contains(@class, 'meta')]")->text();
                    if ($articlePubDate) {
                        [$time, $date] = explode(' ', $articlePubDate);
                        $publicationDate = $this->createDateFromString(str_replace('/', '-', $date).' '.$time);
                    }
                    else {
                        continue;
                    }

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
