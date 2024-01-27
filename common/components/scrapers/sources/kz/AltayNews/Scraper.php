<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\kz\AltayNews;

use common\components\scrapers\common\ArticleBodyScraper;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\NewsCutter;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\BaseUrls;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\XPathParserV2;
use common\components\scrapers\common\symfony\SiteParserModule\Infrastructure\SelectorsRemover;
use common\components\scrapers\common\Config;
use common\components\scrapers\dto\ArticleItem;
use GuzzleHttp\Promise\Coroutine;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Class Scraper
 * @package common\components\scrapers\sources\AltayNews
 *
 * @Config (
 * timezone="Asia/Almaty", urls={
 * "https://altaynews.kz/ru/kriminal/",
 * "https://altaynews.kz/ru/novosti/",
 * "https://altaynews.kz/ru/obrazovanie/",
 * "https://altaynews.kz/ru/regionyi/",
 * "https://altaynews.kz/ru/sport/",
 * "https://altaynews.kz/ru/stati/",
 * "https://altaynews.kz/ru/zdravooxranenie/"
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
        ]
    ];

    /**
     * @var SelectorsRemover
     */
    private $selectorsRemover;

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
        SelectorsRemover $selectorsRemover,
        BaseUrls         $baseUrls,
        XPathParserV2    $XpathParser,
        NewsCutter       $newsCutter,
                         $config = []
    )
    {
        $this->selectorsRemover = $selectorsRemover;
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

            $selector = "//div[contains(@class, 'article page-news__article')]";

            $textNode = $html->filterXPath($selector);

            $newsLinks = $textNode->filterXPath('//a');
            $this->newsCutter->cutterByLinks($newsLinks);

            $this->selectorsRemover->remove('//noscript|//div[@class="article__head"]|//div[@class="article__foot"]', $textNode);

            $text = $textNode->filterXPath(
                "
            //p[not(ancestor::blockquote)]|
            //blockquote[not(@class='wp-embedded-content')]|
            //h1[not(@class='singular__title')]|
            //img|
            //p|
            //div[@class='article__contain']/b|
            //b/following-sibling::text()|
            //figure//img|
            //ul|//ol|
            //iframe|
            //div[contains(concat(' ', @class, ' '), ' singular__content ')]/child::text()[1]|
            "
            );

            $this->baseUrls->addImageUrl('https://altaynews.kz');

            return $this->XpathParser->parse($text, self::OVERRIDE_REPLACE_TAGS, $this->baseUrls);
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//div[@class = 'regions-block']";
            $articlesNode = $html->filterXPath($selector)->first();

            $baseUrl = 'https://altaynews.kz';

            $articles = $articlesNode->filterXPath("//div[contains(@class, 'regions-block__col')]|//a[@class='news-tape__item']");

            $lastAddedPublicationTime = $this->lastPublicationTime;
            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                if ($node->nodeName() === 'a') {
                    $pageHref = $node->attr('href');
                } else {
                    $pageHref = $node->filterXPath("//a[contains(@class, 'news-card')]")->attr('href');
                }
                try {
                    $pageLink = $baseUrl . $pageHref;

                    if ($node->nodeName() === 'a') {
                        $dateStr = $node->filterXPath('//p[@class="news-tape__date"]')->text();
                        [$date, $time] = mb_split(', ', $dateStr);
                        if ($date === 'Сегодня') {
                            $date = date('d-m-Y');
                        }

                        if (!$date || !$time) {
                            continue;
                        }

                        $date = str_replace('.', '-', $date);

                        $publicationDate = $this->createDateFromString(date('Y-m-d H:i', strtotime($date . ' ' . $time)));

                    } else {
                        $dateStr = $node->filterXPath("//p[contains(@class, 'news-card__date')]")->text();
                        $timeStr = $node->filterXPath("//p[contains(@class, 'news-card__time')]")->text();

                        if (!$dateStr || !$timeStr) {
                            continue;
                        }

                        $publicationDate = $this->createDateFromString(date('Y-m-d H:i', strtotime($dateStr . ' ' . $timeStr)));
                    }

                    if ($publicationDate > $lastAddedPublicationTime) {
                        $title = $node->filterXPath("//p[contains(@class, 'news-card__title')]|//p[@class='news-tape__title']")->text();

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
