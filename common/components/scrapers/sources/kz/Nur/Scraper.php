<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\kz\Nur;

use common\components\guzzle\Guzzle;
use common\components\scrapers\common\ArticleBodyScraper;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\ExternalServiceHelper\YoutubeHelper;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\XPathParserV2;
use common\components\scrapers\common\symfony\SiteParserModule\Infrastructure\SelectorsRemover;
use common\components\scrapers\common\Config;
use common\components\scrapers\dto\ArticleBodyNode;
use common\components\scrapers\dto\ArticleItem;
use GuzzleHttp\Promise\Coroutine;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use Symfony\Component\DomCrawler\Crawler;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\NewsCutter;

/**
 * Class Scraper
 * @package common\components\scrapers\sources\kz\Nur
 *
 * @Config (
 * timezone="Asia/Almaty", urls={
 * "https://www.nur.kz/latest/",
 * "https://www.nur.kz/world/",
 * "https://www.nur.kz/showbiz/",
 * "https://www.nur.kz/society/"
 * })
 */
class Scraper extends \common\components\scrapers\common\Scraper implements ArticleBodyScraper
{
    private const REPLACE_TAGS = [
        'iframe' => [
            [
                'contains' => 'riddle.com',
                'attribute' => 'src',
                'valueType' => 'webview',
                'elementName' => 'test',
            ],
        ],
        'img' => [
            [
                'attribute' => 'srcset',
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
     * @var XPathParserV2
     */
    private $XPathParser;

    /**
     * @var YoutubeHelper
     */
    private $youtubeHelper;

    /**
     * @var SelectorsRemover
     */
    private $selectorsRemover;

    public function __construct(
        NewsCutter $newsCutter,
        SelectorsRemover $selectorsRemover,
        XPathParserV2 $XPathParser,
        YoutubeHelper $youtubeHelper,
        $config = []
    )
    {
        $this->selectorsRemover = $selectorsRemover;
        $this->newsCutter = $newsCutter;
        $this->XPathParser = $XPathParser;
        $this->youtubeHelper = $youtubeHelper;

        parent::__construct($config);
    }

    public function parseArticleBody(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));

            $replaceTags = self::REPLACE_TAGS;

            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//main//article";

            $textNode = $html->filterXPath($selector);

            $this->selectorsRemover->remove(
                "
            //a[contains(@class, 'article-read-also')]|
            //p[contains(@class, 'formatted-body__block-hidden')]|
            //*[contains(text(), 'WhatsApp NUR.KZ')]|
            //div[@class='layout-content-type-page__wrapper-block']|
            //ul[@class='breadcrumbs']|
            //div[@class='article__datetime-and-share']|
            //ul[@class='article-footer__tags']|
            //aside
            ",
                $textNode
            );

            $newsLinks = $textNode->filterXPath('//a');
            $this->newsCutter->cutterByLinks($newsLinks);

            $text = $textNode->filterXPath(
                "
                //figure//picture//img|
            //div[contains(@class, 'article-image')]//img|
            //p|
            //ol|//ul|
            //blockquote[contains(@class, 'twitter-tweet')]/a|
            //div[contains(@class, 'fb-post')]|
            //iframe|
            //blockquote[contains(@class, 'instagram-media')]//p/a|
            "
            );

            $result = $this->XPathParser->parse($text, $replaceTags, null);

            $video = $this->parseVideo($textNode->filterXPath('//lite-youtube'));
            if ($video) {
                foreach ($video as $key => $value) {
                    $result->add(new ArticleBodyNode('video', $value));
                }
            }

            yield $result;
        });
    }


    private function parseVideo(Crawler $videoNodes): ?array
    {
        $video = [];
        try {
            $videoNodes->each(
                function (Crawler $videoNode) use (&$video) {
                    $videoId = $videoNode->attr('videoid');
                    if ($videoId) {
                        $video[] = $this->youtubeHelper->generateUrlForId($videoId);
                    }
                }
            );

            return $video;
        } catch (\Throwable $exception) {
            return null;
        }
    }



    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));

            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//div[contains(@class, 'layout-taxonomy-page__content')]|//div[@class='layout-taxonomy-page__content']";
            $articlesNode = $html->filterXPath($selector)->first();

            $articles = $articlesNode->filterXPath("//article[contains(@class, 'block-infinite__item')]|//li//article");
            $lastAddedPublicationTime = $this->lastPublicationTime;
            $result = [];

            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    $linkNode = $node->filterXPath("//a")->first();
                    $pageLink = $linkNode->attr('href');
                    $title = $node->filterXPath("//h3|//h2")->first()->text();

                    $publicationDate = $this->createDateFromString($node->filterXPath('//time')->first()->attr('datetime'));

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

    protected function proxyEnablingAttempt(): ?int
    {
        return Guzzle::PROXY_ALWAYS_ENABLED;
    }
}
