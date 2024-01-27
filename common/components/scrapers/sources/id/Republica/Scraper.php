<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\id\Republica;

use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\XPathParserV2;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\ExternalServiceHelper\InstagramHelper;
use common\components\scrapers\common\services\HashImageService;
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
 * @package common\components\scrapers\sources\id\Republica
 *
 * @Config (timezone="Asia/Jakarta", urls={
 * "https://republika.co.id/kanal/news/politik",
 * "https://republika.co.id/kanal/news/hukum",
 * "https://republika.co.id/kanal/news/pendidikan",
 * "https://republika.co.id/kanal/news/umum",
 * "https://republika.co.id/kanal/news/news-analysis",
 * "https://republika.co.id/kanal/news/universitas-muhammadiyah-malang",
 * "https://republika.co.id/kanal/news/bina-sarana-informatika",
 * "https://republika.co.id/kanal/news/telko-highlight",
 * "https://republika.co.id/kanal/news/cek-viral"
 * })
 */
class Scraper extends \common\components\scrapers\common\Scraper implements ArticleBodyScraper
{

    public const REPLACE_TAGS = [
        'source' => [
            'valueType' => 'video',
            'elementName' => 'video',
            'attribute' => 'src',
        ]
    ];

    private const MONTHS = [
        'January' => 1,
        'February' => 2,
        'March' => 3,
        'April' => 4,
        'May' => 5,
        'June' => 6,
        'July' => 7,
        'August' => 8,
        'September' => 9,
        'October' => 10,
        'November' => 11,
        'December' => 12
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

            $selector = "//meta[@property='og:image']|//div[@class='artikel']";

            $textNode = $html->filterXPath($selector);

            $this->selectorsRemover->remove(
                "//div[@class='picked-article']",
                $textNode
            );

            $newsLinks = $textNode->filterXPath("//a");
            $this->newsCutter->cutterByLinks($newsLinks);

            $text = $textNode->filterXPath(
                "
            //meta|
            //img|
            //p|
            //ul|
            //ol|
            //a|
            //h4|//h1|//h2|
            //video//source
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

            $selector = "//div[@class='wrap-latest']";

            $articlesNode = $html->filterXPath($selector);

            $articles = $articlesNode->filterXPath("//div[@class='conten1']");

            $lastAddedPublicationTime = $this->lastPublicationTime;

            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    $linkNode = $node->filterXPath("//h2//a")->first();
                    $pageLink = $linkNode->attr('href');
                    $title = $linkNode->text();

                    $dateString = $node->filterXPath('//div[@class="date"]')->first()->text();

                    if (stripos($dateString, 'yang lalu') !== false) {
                        $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                        if ($this->isNeedSkipArticle($pageContent)) {
                            continue;
                        }
                        $html = new Crawler();
                        $html->addHtmlContent($pageContent->getBody()->getContents(), 'UTF-8');
                        $articlePubDate = $html->filterXPath("//head//meta[@property='article:published_time']")->first();
                        $dateString = $articlePubDate->attr('content');
                        if (!$dateString) {
                            continue;
                        }
                    }
                    else {
                        $dateString = $this->prepareDateString($dateString);
                    }

                    $publicationDate = $this->createDateFromString($dateString);

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

    private function prepareDateString(string $string): string
    {
        [$date, $time] = mb_split(',', $string);
        [$day, $month, $year] = mb_split(' ', $date);
        $month = self::MONTHS[$month];
        return $day.'-'.$month.'-'.$year.$time;
    }
}