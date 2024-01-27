<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\ru\Konkurent;

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
 * @package common\components\scrapers\sources\ru\Konkurent;
 *
 * @Config (
 * timezone="Asia/Vladivostok", urls={
 * "https://konkurent.ru/news"
 * })
 */
class Scraper extends \common\components\scrapers\common\Scraper implements ArticleBodyScraper
{

    private const REPLACE_TAGS = [
        'a' => [
            [
                'contains' => 'youtu',
                'attribute' => 'href',
                'valueType' => 'href',
                'elementName' => 'video',
            ],
        ],
    ];


    private const MONTH = [
        'января' => '-01.',
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
        BaseUrls $baseUrls,
        XPathParserV2 $XpathParser,
        NewsCutter $newsCutter,
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

            $selector = "//div[@class = 'b-article']";

            $textNode = $html->filterXPath($selector)->first();

            $this->selectorsRemover->remove('//div[@class="b-channels"]', $textNode);

            $newsLinks = $textNode->filterXPath("//div[contains(@class, 'b-article__text')]//a");
            $this->newsCutter->cutterByLinks($newsLinks);

            $text = $textNode->filterXPath(
                "
                //img|
                //div[contains(@class, 'b-article__text')]//p|
                //div[contains(@class, 'b-article__text')]//p//a|
                //div[contains(@class, 'b-article__text')]//ul|
                //div[contains(@class, 'b-article__text')]//ol|
                //div[contains(@class, 'b-article__text')]//blockquote|
            "
            );

            $this->baseUrls->addImageUrl('https://konkurent.ru');

            $result = $this->XpathParser->parse($text, self::REPLACE_TAGS, $this->baseUrls);

            return $result;
        });
    }


    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//div[@class='b-articles']";
            $articlesNode = $html->filterXPath($selector)->first();
            $this->selectorsRemover->remove('//div[contains(@class, "b-pagination")]', $articlesNode);

            $articles = $articlesNode->filterXPath('//a[@href]');

            $lastAddedPublicationTime = $this->lastPublicationTime;

            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    $articlePubDate = $node->filterXPath('//div[@class="b-articles__date"]');
                    $pubDateAttr = $articlePubDate->text();
                    if (!$pubDateAttr) {
                        continue;
                    }
                    $publicationDate = $this->prepareTime($pubDateAttr);

                    if ($publicationDate > $lastAddedPublicationTime) {
                        $title = $node->filterXPath('//div[contains(@class, "b-articles__title")]')->text();
                        $pageLink = $node->filterXPath('//a[@href]')->attr('href');
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

    private function prepareTime(string $rawDatetime): Carbon
    {
        [$day, $time] = mb_split(', ', $rawDatetime);
        [$hour, $minute] = mb_split(':', $time);
        if ('Сегодня' === $day) {
            $day = 0;
        }
        elseif ('Вчера' === $day) {
            $day = -1;
        }
        else {
            [$day, $month, $year] = mb_split(' ', $day);
            $time = new \DateTime($day . self::MONTH[$month] . $year . ' ' . $time);
            return $this->createDateFromString($time->format('Y-m-d H:i:s'));
        }


        $time = new \DateTime();
        $time->modify("- $day days");
        $time->setTime((int)$hour, (int)$minute);
        $time->setDate((int)$time->format('Y'), (int)$time->format('m'), (int)$time->format('d'));

        return $this->createDateFromString($time->format('Y-m-d H:i:s'));
    }
}
