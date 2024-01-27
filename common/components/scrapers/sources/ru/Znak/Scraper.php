<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\ru\Znak;

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
 * @package common\components\scrapers\sources\ru\Znak
 *
 * @Config (
 * timezone="Europe/Moscow", urls={
 * "https://www.znak.com/podcast/",
 * "https://www.znak.com/smarterliving/",
 * "https://www.znak.com/ecology/",
 * "https://www.znak.com/tech/",
 * "https://www.znak.com/infotainment/",
 * "https://www.znak.com/column/",
 * "https://www.znak.com/realty/"
 * })
 */
class Scraper extends \common\components\scrapers\common\Scraper implements ArticleBodyScraper
{

    private const OVERRIDE_REPLACE_TAGS = [
        'strong' => [
            [
                'valueType' => 'text',
                'elementName' => 'quote',
            ],
        ],
        'div' => [
            [
                'attribute' => 'data-id',
                'valueType' => 'twitter-id',
                'elementName' => 'twitter',
            ],
            [
                'attribute' => 'data-downloadurl',
                'valueType' => 'proxyJpg',
                'elementName' => 'image',
            ],
        ],
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
        return $this->sendAsyncRequestWithProxy(new Request('GET', $url))->then(function (ResponseInterface $response) {
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $raplaceTags = self::OVERRIDE_REPLACE_TAGS;

            $selector = "//section[contains(@class, 'article-wrapper')]//article";

            $textNode = $html->filterXPath($selector)->first();

            $this->selectorsRemover->remove(
                "
            //section[contains(@class, 'footer')]|
            //*[contains(@class, 'donate_text')]|
            ",
                $textNode
            );

            $newsLinks = $textNode->filterXPath('//a');
            $this->newsCutter->cutterByLinks($newsLinks);

            $text = $textNode->filterXPath(
                "
            //div[contains(@class, 'img ')]//img|
            //div[contains(@class, 'img')]//div[@data-downloadurl]|
            //p|
            //h3|
            //strong|
            //div[contains(@class, 'tw-post')]|
            //div[contains(@class, 'embedded')]//iframe|
            //script[@data-telegram-post]|
            //section[contains(@class, 'photo')]
            "
            );

            $result = $this->XPathParser->parse($text, $raplaceTags);
            $carousel = $this->parseGallery($textNode->filterXPath("//section[contains(@class, 'photo')]"));
            if ($carousel) {
                $result->add($carousel);
            }

            return $result;
        });
    }

    private function parseGallery(Crawler $photoSection): ?ArticleBodyNode
    {
        try {
            if ($photoSection->count()) {
                $imagesNodes = $photoSection->filterXPath('//a');
                $images = [];
                $imagesNodes->each(
                    function (Crawler $node) use (&$images) {
                        $imgSrc = $node->attr('href');
                        if (0 === strpos($imgSrc, '//')) {
                            $imgSrc = 'https:'.$imgSrc;
                        }
                        $images[] = $this->hashImageService->hashImage($imgSrc);
                    }
                );

                return new ArticleBodyNode('carousel', $images);
            } else {
                return null;
            }
        } catch (\Throwable $exception) {
            return null;
        }
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));

            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents());

            $selector = "//section[@class='flex x4']";
            $articlesNode = $html->filterXPath($selector);

            $articles = $articlesNode->filterXPath("//a[contains(@class, ' x1 ')]|//a[contains(@class, ' x2 ')]");

            $lastAddedPublicationTime = $this->lastPublicationTime;
            $basePath = 'https://www.znak.com';
            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    $pageLink = $basePath.$node->attr('href');
                    $title = $node->filterXPath("//h5")->text();

                    $articlePubDate = $node->filterXPath("//time");
                    $pubDateAttr = $articlePubDate->attr("datetime");

                    if (!$pubDateAttr) {
                        continue;
                    }
                    $publicationDate = $this->createDateFromString($pubDateAttr, false);

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
