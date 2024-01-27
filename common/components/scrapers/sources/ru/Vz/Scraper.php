<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\ru\Vz;

use Carbon\Carbon;
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
 * @package common\components\scrapers\sources\ru\Vz
 *
 * @Config (
 * timezone="Europe/Moscow", urls={
 * "https://vz.ru/economy/",
 * "https://vz.ru/politics/",
 * "https://vz.ru/society/",
 * "https://vz.ru/world/",
 * "https://vz.ru/incidents/",
 * "https://vz.ru/opinions/",
 * "https://vz.ru/video/",
 * "https://vz.ru/photo"
 * })
 */
class Scraper extends \common\components\scrapers\common\Scraper implements ArticleBodyScraper
{
    private const REPLACE_TAGS = [
        'iframe' => [
            [
                'contains' => 'ren.tv',
                'attribute' => 'src',
                'valueType' => 'video',
                'elementName' => 'video',
            ],
            [
                'contains' => '1plus1',
                'attribute' => 'src',
                'valueType' => 'video',
                'elementName' => 'video',
            ],
        ],
    ];

    private const MONTHS = [
        'января' => '-01-',
        'февраля' => '-02-',
        'марта' => '-03-',
        'апреля' => '-04-',
        'мая' => '-05-',
        'июня' => '-06-',
        'июля' => '-07-',
        'августа' => '-08-',
        'сентября' => '-09-',
        'октября' => '-10-',
        'ноября' => '-11-',
        'декабря' => '-12-',
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
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $replaceTags = self::REPLACE_TAGS;

            $selector = "//article//div[contains(@class, 'newtext')]";

            $textNode = $html->filterXPath($selector)->first();

            $this->selectorsRemover->remove(
                "
            //iframe[contains(@src, 'giraff')]|
            //p//a[contains(text(), 'Смотрите ещё больше')]|
            //blockquote[contains(@class, 'twitter-tweet')]//text()|
            //blockquote[contains(@class, 'instagram-media')]//text()|
            //div[contains(@class, 'fb-post')]//text()|
            ",
                $textNode
            );

            $newsLinks = $textNode->filterXPath('//a');
            $this->newsCutter->cutterByLinks($newsLinks);

            $text = $textNode->filterXPath(
                "
            //p[not(ancestor::blockquote)]|
            //blockquote|
            //dl[contains(@class, 'video')]//iframe|
            //dl[contains(@class, 'video')]//img|
            //tbody//img|
            //blockquote[contains(@class, 'twitter-tweet')]/a|
            //blockquote[contains(@class, 'instagram-media')]|
            //script[@data-telegram-post]|
            "
            );

            return $this->XPathParser->parse($text, $replaceTags, null, true);
        });
    }


    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));

            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents());

            $selector = "//dl[@class='lenta']|//table[@class='sectionpage']";
            $articlesNode = $html->filterXPath($selector);

            $articles = $articlesNode->filterXPath("//dt//a|//div[contains(@class, 'othnews ')]");

            $lastAddedPublicationTime = $this->lastPublicationTime;
            $basePath = 'https://vz.ru';
            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    if ($node->nodeName() === 'a') {
                        $linkNode = $node->attr('href');
                        $pageLink = $basePath . $linkNode;
                        $title = $node->text();
                    }
                    else {
                        $linkNode = $node->filterXPath("//a")->attr('href');
                        $pageLink = $basePath . $linkNode;
                        $title = $node->filterXPath("//a")->text();
                    }

                    if (!$title) {
                        continue;
                    }

                    $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                    if ($this->isNeedSkipArticle($pageContent)) {
                        continue;
                    }
                    $html = new Crawler();
                    $html->addHtmlContent($pageContent->getBody()->getContents(), 'UTF-8');

                    $articlePubDate = $html->filterXPath('//article')->first()->filterXPath("//p[@class='extra']|//td[@class='extra']")->first();
                    if (!$articlePubDate->count()) {
                        continue;
                    }
                    $pubDateAttr = $articlePubDate->text();

                    if (!$pubDateAttr) {
                        continue;
                    }
                    $publicationDate = $this->prepareTime($pubDateAttr);

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

    private function prepareTime(string $rawDatetime): Carbon
    {
        preg_match('#(?<d>\d{1,2}) (?<m>[а-я]+) (?<y>\d{4}), (?<time>\d{1,2}:\d{1,2})#iu', $rawDatetime, $m);
        return $this->createDateFromString($m['y'] . self::MONTHS[$m['m']] . $m['d'] . ' ' . $m['time']);
    }
}
