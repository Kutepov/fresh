<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\ua\Ukrinform\uk;

use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\BaseUrls;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\CatchExeptionalParser;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\XPathParserV2;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\NewsCutter;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\SelectorsRemover;
use common\components\scrapers\common\ArticleBodyScraper;
use common\components\scrapers\dto\ArticleItem;
use GuzzleHttp\Promise\Coroutine;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DomCrawler\Crawler;
use common\components\scrapers\common\Config;

/**
 * Class Scraper
 * @package common\components\scrapers\sources\ua\Ukrinform\uk
 *
 * @Config (timezone="Europe/Kiev", urls={
 "https://www.ukrinform.ua/rubric-ato",
"https://www.ukrinform.ua/rubric-crimea",
"https://www.ukrinform.ua/rubric-culture",
"https://www.ukrinform.ua/rubric-diaspora",
"https://www.ukrinform.ua/rubric-economy",
"https://www.ukrinform.ua/rubric-kyiv",
"https://www.ukrinform.ua/rubric-polytics",
"https://www.ukrinform.ua/rubric-regions",
"https://www.ukrinform.ua/rubric-society",
"https://www.ukrinform.ua/rubric-sports",
"https://www.ukrinform.ua/rubric-technology",
"https://www.ukrinform.ua/rubric-tourism",
"https://www.ukrinform.ua/rubric-world"
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
     * @var BaseUrls
     */
    private $BaseUrls;

    /**
     * @var CatchExeptionalParser
     */
    private $catchExeptionalParser;

    /**
     * @var NewsCutter
     */
    private $newsCutter;

    public function __construct(
        SelectorsRemover $selectorsRemover,
        NewsCutter $newsCutter,
        XPathParserV2 $XPathParser,
        BaseUrls $baseUrls,
        CatchExeptionalParser $catchExeptionalParser,
        $config = []
    )
    {
        $this->selectorsRemover = $selectorsRemover;
        $this->XPathParser = $XPathParser;
        $this->newsCutter = $newsCutter;
        $this->BaseUrls = $baseUrls;
        $this->catchExeptionalParser = $catchExeptionalParser;

        parent::__construct($config);
    }

    public function parseArticleBody(string $url): PromiseInterface
    {
        return $this->sendAsyncRequestWithProxy(new Request('GET', $url))->then(function (ResponseInterface $response) {

            $pageContent = $response->getBody()->getContents();

            $html = new Crawler();
            $html->addHtmlContent($pageContent, 'UTF-8');


            $selector = '//article[contains(@class, "news")]';

            $textNode = $html->filterXPath($selector)->first();

            $this->catchExeptionalParser->catchexeption($textNode->filterXPath('//a'));

            $this->selectorsRemover->remove(
                '//div[contains(@class, "single-info")]|//div[contains(@class, "quote-with-img__info")]|
            //section[contains(@class, "read")]|
            //div[@data-name="int_hidden"]',
                $textNode
            );

            $newsLinks = $textNode->filterXPath("//div[contains(@class, 'newsText')]//a");
            $this->newsCutter->cutterByLinks($newsLinks);

            $text = $textNode->filterXPath(
                '//img[contains(@class, "newsImage")][1]|
            //div[contains(@class, "newsText")]//p|
            //div[contains(@class, "newsText")]//div[contains(@class, "newsHeading")]|
            //div[contains(@class, "newsText")]//img|
            //div[contains(@class, "newsText")]//iframe[contains(@src, "youtube.com")]|
            //div[contains(@class, "newsText")]//blockquote[not(contains(@class, "twitter-tweet"))]|
            //div[contains(@class, "newsText")]//ul|
            //div[contains(@class, "newsText")]//video|
            //a|
            //script[@data-telegram-post]
            '
            );

            $result = $this->XPathParser->parse($text, null, $this->BaseUrls, false, null, true, true);

            $description = $this->XPathParser->parseDescription($html, '//article[@class="news"]//p[1]')->getNodes()[0]->getValue();
            $result->setDescription($description);

            return $result;
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addHtmlContent($pageContent->getBody()->getContents(), 'UTF-8');

            $selector = "//div[@class='othersBody']";
            $articlesNode = $html->filterXPath($selector)->first();
            $this->selectorsRemover->remove("//div[@class='othersDay']", $articlesNode);

            $articles = $articlesNode->filterXPath("//div");

            $lastAddedPublicationTime = $this->lastPublicationTime;

            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {

                    $linkNode = $node->filterXPath("//a")->first();
                    $pageLink = $linkNode->attr('href');
                    if (!preg_match('#^https?://#', $pageLink)) {
                        $pageLink = $this->getHost() . $pageLink;
                    }

                    $this->selectorsRemover->remove('//span[@class="otherTime"]|//span[@class="othersPrefix"]', $node);

                    $title = $node->filterXPath("//a")->first()->text();


                    $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                    if ($this->isNeedSkipArticle($pageContent)) {
                        continue;
                    }
                    $html = new Crawler($pageContent->getBody()->getContents());
                    $articleData = json_decode($html->filterXPath("//script[@type = 'application/ld+json']")->first()->text());

                    $articlePubDate = $articleData->datePublished;

                    if (!$articlePubDate) {
                        continue;
                    }

                    $publicationDate = $this->createDateFromString($articlePubDate);

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

    protected function getHost(): string
    {
        return 'https://www.ukrinform.ua';
    }
}