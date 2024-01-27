<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\ru\Ng;

use common\components\scrapers\common\ArticleBodyScraper;
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
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\NewsCutter;

/**
 * Class Scraper
 * @package common\components\scrapers\sources\ru\Ng
 *
 * @Config (
 * timezone="Europe/Moscow", urls={
 * "https://www.ng.ru/editorial/",
 * "https://www.ng.ru/politics/",
 * "https://www.ng.ru/economics/",
 * "https://www.ng.ru/regions/",
 * "https://www.ng.ru/cis/",
 * "https://www.ng.ru/world/",
 * "https://www.ng.ru/culture/",
 * "https://www.ng.ru/ideas/",
 * "https://www.ng.ru/science/",
 * "https://www.ng.ru/education/",
 * "https://www.ng.ru/health/",
 * "https://www.ng.ru/armies/",
 * "https://www.ng.ru/week/",
 * "https://www.ng.ru/cinematograph/",
 * "https://www.ng.ru/style/",
 * "https://www.ng.ru/titus/",
 * "https://www.ng.ru/columnist/",
 * "https://www.ng.ru/society/",
 * "https://www.ng.ru/vision/"
 * })
 */
class Scraper extends \common\components\scrapers\common\Scraper implements ArticleBodyScraper
{

    private const OVERRIDE_REPLACE_TAGS = [];

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
     * @var SelectorsRemover
     */
    private $selectorsRemover;

    /**
     * @var BaseUrls
     */
    private $baseUrls;

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
        return $this->sendAsyncRequestWithProxy(new Request('GET', $url))->then(function (ResponseInterface $response) {
            $html = new Crawler($response->getBody()->getContents());

            $raplaceTags = self::OVERRIDE_REPLACE_TAGS;

            $selector = "//article[@class = 'typical']";

            $textNode = $html->filterXPath($selector);

            $this->selectorsRemover->remove(
                "
            //span[@class='descrPhoto']
            ",
                $textNode
            );

            $newsLinks = $textNode->filterXPath('//a');
            $this->newsCutter->cutterByLinks($newsLinks);

            $imagesNodes = $textNode->filterXPath('//img');
            $this->newsCutter->cutterNewsWithoutImages($imagesNodes);

            $text = $textNode->filterXPath(
                "
            //img|
            //p|
            //ul|
            //ol|
            //iframe|
            //blockquote[contains(@class, 'instagram-media')]|
            //blockquote[contains(@class, 'twitter-tweet')]//a|
            "
            );

            $this->baseUrls->addImageUrl('https://www.ng.ru');

            $result = $this->XPathParser->parse($text, $raplaceTags, $this->baseUrls);

            return $result;
        });
    }


    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));

            $html = new Crawler($response->getBody()->getContents());

            $selector = "//div[@class='content']//div[@class='content']";
            $articlesNode = $html->filterXPath($selector);

            $articles = $articlesNode->filterXPath("//div[contains( concat(' ', normalize-space(@class), ' '), ' anonce ')]");

            $lastAddedPublicationTime = $this->lastPublicationTime;
            $basePath = 'https://www.ng.ru';
            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    $linkNode = $node->filterXPath('//h3/a');
                    $pageLink = $basePath.$linkNode->attr('href');
                    $title = $linkNode->text();
                    $previewNode = $node->filterXPath('//img');
                    if (!$previewNode->count()) {
                        continue;
                    }

                    $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                    if ($this->isNeedSkipArticle($pageContent)) {
                        continue;
                    }
                    $html = new Crawler($pageContent->getBody()->getContents());

                    $articlePubDate = $html->filterXPath("//p[@class='info']//span[@class='date']");
                    $pubDateAttr = $articlePubDate->text();
                    if (!$pubDateAttr) {
                        continue;
                    }
                    $publicationDate = $this->createDateFromString($pubDateAttr);

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
