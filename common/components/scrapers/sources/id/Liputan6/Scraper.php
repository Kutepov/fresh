<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\id\Liputan6;

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
 * @package common\components\scrapers\sources\id\Liputan6
 *
 * @Config (timezone="Asia/Jakarta", urls={
 * "https://www.liputan6.com/news",
 * "https://www.liputan6.com/bisnis",
 * "https://www.liputan6.com/saham",
 * "https://www.liputan6.com/showbiz",
 * "https://www.liputan6.com/bola",
 * "https://www.liputan6.com/tekno",
 * "https://www.liputan6.com/cek-fakta",
 * "https://hot.liputan6.com/",
 * "https://www.liputan6.com/regional",
 * "https://www.liputan6.com/otomotif",
 * "https://www.liputan6.com/disabilitas",
 * "https://www.liputan6.com/lifestyle",
 * "https://www.liputan6.com/health",
 * "https://www.liputan6.com/citizen6",
 * "https://www.liputan6.com/pilkada"
 * })
 */
class Scraper extends \common\components\scrapers\common\Scraper implements ArticleBodyScraper
{
    private const REPLACE_TAGS = [
        'iframe' => [
            [
                'contains' => 'vidio.com',
                'attribute' => 'src',
                'valueType' => 'video',
                'elementName' => 'video',
            ]
        ],
    ];

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
        XPathParserV2 $XPathParserV2,
        $config = []
    )
    {
        $this->selectorsRemover = $selectorsRemover;
        $this->newsCutter = $newsCutter;
        $this->XPathParser = $XPathParserV2;

        parent::__construct($config);
    }

    public function parseArticleBody(string $url): PromiseInterface
    {
        return $this->sendAsyncRequestWithProxy(new Request('GET', $url))->then(function (ResponseInterface $response) {
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//div[@class='read-page--content']|//div[contains(@class, 'read-page--photo-tag--slider__top ')]";

            $textNode = $html->filterXPath($selector)->first();

            $newsLinks = $textNode->filterXPath("//a");
            $this->newsCutter->cutterByLinks($newsLinks);

            $this->selectorsRemover->remove(
                "//p[contains(@class, 'read-page--video-gallery--item__video-caption_read-video-article')]|
                            //div[@class='advertisement-placeholder']",
                $textNode
            );

            $text = $textNode->filterXPath(
                "
            //img|//figure//div//picture//img|
            //video|
            //iframe|
            //p|
            //ul|
            //ol|
            //a|
            //h4|//em|//strong|//h1
"
            );

            $imageNodes = $textNode->filterXPath('//img');
            $isNeedPrviewImg = !$imageNodes->count();

            return $this->XPathParser->parse($text, self::REPLACE_TAGS, null, $isNeedPrviewImg);
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $contents = $pageContent->getBody()->getContents();
            $html->addHtmlContent($contents, 'UTF-8');

            $selector = "//div[@class='articles']";

            $articlesNode = $html->filterXPath($selector);

            $articles = $articlesNode->filterXPath("//article");

            $lastAddedPublicationTime = $this->lastPublicationTime;

            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    $linkNode = $node->filterXPath("//h4//a")->first();
                    $pageLink = $linkNode->attr('href');
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
                    $this->logArticleItemException($exception, $pageLink);
                }
            }

            yield $result;
        });
    }
}