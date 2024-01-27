<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\kz\Info365;

use Carbon\Carbon;
use common\components\scrapers\common\ArticleBodyScraper;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\NewsCutter;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\BaseUrls;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\XPathParserV2;
use common\components\scrapers\common\symfony\SiteParserModule\Infrastructure\SelectorsRemover;
use common\components\scrapers\common\services\HashImageService;
use common\components\scrapers\common\Config;
use common\components\scrapers\dto\ArticleItem;
use GuzzleHttp\Promise\Coroutine;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Class Scraper
 * @package common\components\scrapers\sources\kz\Info365;
 *
 * @Config (
 * timezone="Asia/Almaty", urls={
 * "https://365info.kz/"
 * })
 */
class Scraper extends \common\components\scrapers\common\Scraper implements ArticleBodyScraper
{

    /**
     * @var XPathParserV2
     */
    private $XpathParser;

    /**
     * @var NewsCutter
     */
    private $newsCutter;

    /**
     * @var BaseUrls
     */
    private $baseUrls;

    public function __construct(
        BaseUrls $baseUrls,
        XPathParserV2 $XpathParser,
        NewsCutter $newsCutter,
        $config = []
    )
    {
        $this->baseUrls = $baseUrls;
        $this->newsCutter = $newsCutter;
        $this->XpathParser = $XpathParser;

        parent::__construct($config);
    }


    public function parseArticleBody(string $url): PromiseInterface
    {
        return $this->sendAsyncRequestWithProxy(new Request('GET', $url))->then(function (ResponseInterface $response) {
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//div[contains(@class, 'layout-singular')]";

            $textNode = $html->filterXPath($selector);

            $newsLinks = $textNode->filterXPath('//a');
            $this->newsCutter->cutterByLinks($newsLinks);

            $text = $textNode->filterXPath(
                "
            //p[not(ancestor::blockquote)]|
            //blockquote[not(@class='wp-embedded-content')]|
            //h1[not(@class='singular__title')]|
            //img|
            //figure//img|
            //ul|//ol|
            //iframe|
            //div[contains(concat(' ', @class, ' '), ' singular__content ')]/child::text()[1]|
            "
            );

            $this->baseUrls->addImageUrl('https://24.kz');
            $imageNodes = $textNode->filterXPath(
                '
            //img|
            //figure//img|
            '
            );
            $isNeedPrviewImg = !$imageNodes->count();

            return $this->XpathParser->parse($text, null, $this->baseUrls, $isNeedPrviewImg);
        });
    }


    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//div[contains(@class, 'lastnews__items lastnews-js')]";
            $articlesNode = $html->filterXPath($selector)->first();

            $articles = $articlesNode->filterXPath('//div[contains(@class, "lastnews__item d-none")]');

            $lastAddedPublicationTime = $this->lastPublicationTime;
            $lastAddedPublicationTime = $lastAddedPublicationTime->setTimezone($this->timezone);
            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {

                    $pageLink = $node->filterXPath('//a')->attr('href');

                    $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                    if ($this->isNeedSkipArticle($pageContent)) {
                        continue;
                    }
                    $html = new Crawler($pageContent->getBody()->getContents());
                    $articleData = json_decode($html->filterXPath("//script[@type = 'application/ld+json']")->first()->text(), true);

                    if (!isset($articleData['@graph'][2]['datePublished'])) {
                        continue;
                    }
                    $publicationDate = $this->createDateFromString($articleData['@graph'][2]['datePublished']);

                    if ($publicationDate > $lastAddedPublicationTime) {
                        $title = $node->filterXPath('//div[@class="lastnews__item-title"]')->text();
                        if (!$pageLink) {
                            continue;
                        }

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
