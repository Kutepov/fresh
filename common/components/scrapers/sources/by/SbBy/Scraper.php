<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\by\SbBy;

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
 * @package common\components\scrapers\sources\by\SbBy
 *
 * @Config (
 * timezone="Europe/Minsk", urls={
 * "https://www.sb.by/blog/",
 * "https://www.sb.by/articles/main_culture/",
 * "https://www.sb.by/articles/drive/",
 * "https://www.sb.by/articles/main_economy/",
 * "https://www.sb.by/exclusive/",
 * "https://www.sb.by/articles/health/",
 * "https://www.sb.by/articles/main_Incidents/",
 * "https://www.sb.by/articles/kosmos/",
 * "https://www.sb.by/articles/mozaika-zhizni/",
 * "https://www.sb.by/articles/main_policy/",
 * "https://www.sb.by/articles/shpilki/",
 * "https://www.sb.by/articles/main_society/",
 * "https://www.sb.by/articles/zhurnal-spetsnaz/",
 * "https://www.sb.by/articles/main_sport/",
 * "https://www.sb.by/articles/telenedelya/",
 * "https://www.sb.by/articles/main_world/"
 * })
 */
class Scraper extends \common\components\scrapers\common\Scraper implements ArticleBodyScraper
{

    private const OVERRIDE_REPLACE_TAGS = [
        'b' => [
            [
                'valueType' => 'text',
                'elementName' => 'paragraph',
            ],
        ],
        'i' => [
            [
                'valueType' => 'text',
                'elementName' => 'paragraph',
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
        $this->baseUrls = $baseUrls;
        $this->hashImageService = $hashImageService;
        $this->selectorsRemover = $selectorsRemover;
        $this->newsCutter = $newsCutter;
        $this->XPathParser = $XPathParser;

        parent::__construct($config);
    }

    public function parseArticleBody(string $url): PromiseInterface
    {
        return $this->sendAsyncRequestWithProxy(new Request('GET', $url))->then(function (ResponseInterface $response) {
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $raplaceTags = self::OVERRIDE_REPLACE_TAGS;

            $selector = "//div[contains(@class, 'js-mediator-article')]";

            $textNode = $html->filterXPath($selector);

            $this->selectorsRemover->remove(
                "
            //blockquote[contains(@class, 'instagram-media')]//text()|
            //blockquote[contains(@class, 'twitter-tweet')]//text()|
            //div[contains(@class, 'b-youtube-sbscr')]|
            ",
                $textNode
            );

            $newsLinks = $textNode->filterXPath('//a');
            $this->newsCutter->cutterByLinks($newsLinks);

            $text = $textNode->filterXPath(
                "
            //img|
            //b[not(ancestor::p)]|
            //i[not(ancestor::p)]|
            //br/following-sibling::text()|
            //div/following-sibling::text()|
            //div/text()|
            //i/following-sibling::text()|
            //iframe|
            //blockquote[contains(@class, 'instagram-media')]|
            //blockquote[contains(@class, 'twitter-tweet')]/a|
            //blockquote|
            "
            );

            $this->baseUrls->addImageUrl('https://www.sb.by');

            $imageNodes = $textNode->filterXPath(
                '
            //img|
            '
            );
            $isNeedPrviewImg = !$imageNodes->count();

            $result = $this->XPathParser->parse($text, $raplaceTags, $this->baseUrls, $isNeedPrviewImg);

            return $result;
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));

            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//div[contains(@class, 'new-article')]";
            $articlesNode = $html->filterXPath($selector)->first();

            $articles = $articlesNode->filterXPath('//div[contains(@class, "media-old")]');

            $lastAddedPublicationTime = $this->lastPublicationTime;

            $baseUrl = 'https://www.sb.by';

            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    $articlePubDate = $node->filterXPath('//div[@data-time-add]');
                    $pubDateAttr = $articlePubDate->attr('data-time-add');
                    if (!$pubDateAttr) {
                        continue;
                    }
                    $publicationDate = $this->createDateFromString($pubDateAttr);

                    if ($publicationDate > $lastAddedPublicationTime) {
                        $title = $node->filterXPath('//div[contains(@class, "title")]')->text();
                        $pageLink = $baseUrl . $node->filterXPath('//a[contains(@class, "link-main")]')->attr('href');

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
