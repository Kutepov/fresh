<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\ru\Ria;

use common\components\guzzle\Guzzle;
use common\components\scrapers\common\ArticleBodyScraper;
use common\components\scrapers\common\helpers\PreviewHelper;
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
 * @package common\components\scrapers\sources\ru\Ria
 *
 * @Config (
 * timezone="Europe/Moscow", urls={
 * "https://ria.ru/politics/",
 * "https://ria.ru/world/",
 * "https://ria.ru/economy/",
 * "https://ria.ru/society/",
 * "https://ria.ru/incidents/",
 * "https://ria.ru/defense_safety/",
 * "https://ria.ru/science/",
 * "https://ria.ru/culture/",
 * "https://ria.ru/religion/",
 * "https://rsport.ria.ru/",
 * "https://ria.ru/tourism/"
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
     * @var XPathParserV2
     */
    private $XPathParser;

    /**
     * @var SelectorsRemover
     */
    private $selectorsRemover;

    public function __construct(
        NewsCutter $newsCutter,
        SelectorsRemover $selectorsRemover,
        XPathParserV2 $XPathParser,
        $config = []
    )
    {
        $this->selectorsRemover = $selectorsRemover;
        $this->newsCutter = $newsCutter;
        $this->XPathParser = $XPathParser;

        parent::__construct($config);
    }

    public function parseArticleBody(string $url): PromiseInterface
    {
        return $this->sendAsyncRequestWithProxy(new Request('GET', $url))->then(function (ResponseInterface $response) {
            $html = new Crawler($response->getBody()->getContents());

            $raplaceTags = self::OVERRIDE_REPLACE_TAGS;

            $selector = "//div[@class = 'article__header']|//div[contains(@class, 'article__body')]|//div[@class='b-longread']";

            $textNode = $html->filterXPath($selector);

            $newsLinks = $textNode->filterXPath('//a');
            $this->newsCutter->cutterByLinks($newsLinks);

            $this->selectorsRemover->remove("//div[@data-type='article']|
            //script|
            //div[@class='article__photo-item-text']
            ", $textNode);

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
            //div[@class='article__text']|
            "
            );

            $result = $this->XPathParser->parse($text, $raplaceTags, null);

            return $result;
        });
    }


    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));

            $html = new Crawler($response->getBody()->getContents());

            $selector = "//div[@class='list list-tags']|//div[@class='cell-list__list']|//div[@class='rubric-list']//div[@class='list']";
            $articlesNode = $html->filterXPath($selector);

            $articles = $articlesNode->filterXPath("//div[@class='list-item']|//div[contains(@class, 'cell-list__item')]");
            $lastAddedPublicationTime = $this->lastPublicationTime;
            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    $linkNode = $node->filterXPath('//a[contains(@class, "list-item__title")]|//a[contains(@class, "cell-list__item-link")]');
                    if ($linkNode->count() === 0) {
                        continue;
                    }
                    $tags = $node->filterXPath("//div[@class='list-item__tags']")->first();
                    if ($tags->count() > 0) {
                        if (stripos($tags->text(), 'Викторины') !== false) {
                            continue;
                        }
                    }
                    $pageLink = $linkNode->attr('href');
                    $title = $linkNode->text();

                    $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                    if ($this->isNeedSkipArticle($pageContent)) {
                        continue;
                    }
                    $html = new Crawler($pageContent->getBody()->getContents());

                    $articlePubDate = $html->filterXPath("//meta[@property='article:published_time']");
                    $pubDateAttr = $articlePubDate->attr('content');
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

    public function proxyEnablingAttempt(): ?int
    {
        return Guzzle::PROXY_ALWAYS_ENABLED;
    }
}
