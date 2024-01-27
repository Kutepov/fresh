<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\ua\Epravda\uk;

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
 * @package common\components\scrapers\sources\ua\Epravda\uk
 *
 * @Config (timezone="Europe/Kiev", urls={
 * "https://www.epravda.com.ua/news/"
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

    public function __construct(
        SelectorsRemover $selectorsRemover,
        NewsCutter $newsCutter,
        XPathParserV2 $XPathParser,
        $config = []
    )
    {
        $this->selectorsRemover = $selectorsRemover;
        $this->XPathParser = $XPathParser;
        $this->newsCutter = $newsCutter;

        parent::__construct($config);
    }

    public function parseArticleBody(string $url): PromiseInterface
    {
        return $this->sendAsyncRequestWithProxy(new Request('GET', $url))->then(function (ResponseInterface $response) {

            $replaceTags = self::OVERRIDE_REPLACE_TAGS;

            $html = new Crawler($response->getBody()->getContents());


            $selector = "//div[@class = 'post__text']";

            $textNode = $html->filterXPath($selector)->first();

            $this->selectorsRemover->remove(
                "
                //p[not(*)][not(normalize-space())]|
            //p[contains(text(), 'Читайте детальніше:')]|
            //p[contains(text(), 'Читайте подробнее:')]|
            //p[contains(text(), 'Вас також може зацікавити:')]|
            //p[contains(text(), 'Вас также может заинтересовать:')]|
            //p[contains(text(), 'Знати більше:')]|
            //div[contains(@class, 'post__text')]//p/strong/em[contains(text(), 'Читайте також')]|
            //div[contains(@class, 'post__text')]//p/strong/em[contains(text(), 'Читайте также')]|
            //div[contains(@class, 'post__text')]//p/strong/em[contains(text(), 'Знати більше')]|
            //div[contains(@class, 'post__text')]//p/strong/em[contains(text(), 'Знати більше')]//following::p[1]|
            //div[contains(@class, 'post__text')]//p/strong/em[contains(text(), 'Знать больше')]|
            //div[contains(@class, 'post__text')]//p/strong/em[contains(text(), 'Знать больше')]//following::p[1]
                ",
                $textNode
            );

            $newsLinks = $textNode->filterXPath("//div[contains(@class, 'post__text')]//a");
            $this->newsCutter->cutterByLinks($newsLinks);

            $text = $textNode->filterXPath(
                "                        
            //div[contains(@class, 'post__text')]//ul|
            //div[contains(@class, 'post__text')]//ol|
            //div[contains(@class, 'post__text')]/p|
            //div[contains(@class, 'post__text')]//blockquote[not(contains(@class, 'twitter-tweet'))]|
            //div[contains(@class, 'post__text')]//blockquote//a|
            //div[contains(@class, 'post__text')]//iframe|
            //div[contains(@class, 'post__text')]//script|
            //div[contains(@class, 'post__video')]/iframe|
            //div[contains(@class, 'post_text')]//ul|
            //div[contains(@class, 'post_text')]//ol|
            //div[contains(@class, 'post_text')]/p|
            //div[contains(@class, 'post_text')]//blockquote[not(contains(@class, 'twitter-tweet'))]|
            //div[contains(@class, 'post_text')]//blockquote//a|
            //div[contains(@class, 'post_text')]//iframe|
            //div[contains(@class, 'post_text')]//script|
            //div[contains(@class, 'post_video')]/iframe|
            //div[contains(@class, 'post_photo_news')]/img|
            //div[contains(@class, 'image-box')]//img|
            //table[contains(@class, ' table')]|
            "
            );

            $articleBody = $this->XPathParser->parse($text, $replaceTags, null, false, null, true, true);
            $description = $this->XPathParser->parseDescription($html, '//div[@class="post__text"]//p[1]')->getNodes()[0]->getValue();
            $articleBody->setDescription($description);
            return $articleBody;
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler($pageContent->getBody()->getContents());

            $selector = "//div[contains(@class, 'news_list')]";
            $articlesNode = $html->filterXPath($selector)->first();

            $articles = $articlesNode->filterXPath("//div[contains(@class, 'article_news')]");

            $lastAddedPublicationTime = $this->lastPublicationTime;

            $baseUrl = 'https://www.epravda.com.ua';

            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {

                    $linkNode = $node->filterXPath('//a')->first();
                    $this->selectorsRemover->remove("//em", $linkNode);

                    $pageLink = $linkNode->attr('href');
                    if (!preg_match('#^https?://#i', $pageLink)) {
                        $pageLink = $baseUrl . $pageLink;
                    }
                    $title = $linkNode->text();

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
}