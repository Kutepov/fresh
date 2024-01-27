<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\kz\Sputnik;

use common\components\scrapers\common\ArticleBodyScraper;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\NewsCutter;
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
 * @package common\components\scrapers\sources\kz\Sputnik
 *
 * @Config (
 * timezone="Asia/Almaty", urls={
 * "https://ru.sputnik.kz/politics/",
 * "https://ru.sputnik.kz/sport/"
 * })
 */
class Scraper extends \common\components\scrapers\common\Scraper implements ArticleBodyScraper
{
    private const OVERRIDE_REPLACE_TAGS = [
        'div' => [
            [
                'attribute' => 'class',
                'contains' => 'article__quote',
                'valueType' => 'text',
                'elementName' => 'quote',
            ],
            [
                'valueType' => 'text',
                'elementName' => 'paragraph',
            ],
            [
                'contains' => 'facebook.com',
                'attribute' => 'data-href',
                'valueType' => 'webview',
                'elementName' => 'facebook',
            ],
            [
                'attribute' => 'data-id',
                'valueType' => 'rambler',
                'elementName' => 'video',
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
    private $XPathParser;

    /**
     * @var NewsCutter
     */
    private $newsCutter;

    public function __construct(
        SelectorsRemover $selectorsRemover,
        XPathParserV2 $XPathParserV2,
        NewsCutter $newsCutter,
        $config = []
    )
    {
        $this->selectorsRemover = $selectorsRemover;
        $this->XPathParser = $XPathParserV2;
        $this->newsCutter = $newsCutter;

        parent::__construct($config);
    }


    public function parseArticleBody(string $url): PromiseInterface
    {
        return $this->sendAsyncRequestWithProxy(new Request('GET', $url))->then(function (ResponseInterface $response) {



            $raplaceTags = self::OVERRIDE_REPLACE_TAGS;
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');


            $selector = "//div[@class = 'article ']";

            $textNode = $html->filterXPath($selector);

            $this->selectorsRemover->remove(
                "
            //div[contains(@class, 'b-inject')]|
            //div[@data-type='article']|
            ",
                $textNode
            );

            $newsLinks = $textNode->filterXPath('//a');
            $this->newsCutter->cutterByLinks($newsLinks);

            $text = $textNode->filterXPath(
                "
                //div[@class='article__announce']//img|
            //div[@class='article__block']//img|
            //div[@class='article__block']//p|
            //div[@class='article__block']//ul|
            //div[@class='article__block']//ol|
            //div[@class='article__block']//h3|
            //div[@class='article__block']//img|
            //div[@class='article__block']//p|
            //div[@class='article__block']//ul|
            //div[@class='article__block']//ol|
            //div[@class='article__block']//h3|
            //div[@class='article__block']//a|
            //div[@class='article__block']//div[@class='article__text']|
            //div[@class='article__block']//div[@class='article__quote']|
            "
            );

            return $this->XPathParser->parse($text, $raplaceTags, null, true, null, false);
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//div[@class='list list-tag']";
            $articlesNode = $html->filterXPath($selector);

            $articles = $articlesNode->filterXPath('//div[contains(@class, "list__item")]');

            $baseUrl = 'https://ru.sputnik.kz';

            $lastAddedPublicationTime = $this->lastPublicationTime;

            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    $linkNode = $node->filterXPath('//div[@class="list__content"]//a')->first();
                    $pageLink = $baseUrl.$linkNode->attr('href');
                    $title = $linkNode->text();

                    if (stripos($pageLink, 'longrid') !== false) {
                        continue;
                    }

                    $articlePubDate = $node->filterXPath("//div[contains(@class, 'list__date')]")->attr('data-unixtime');

                    $publicationDate = $this->createDateFromString(date('Y-m-d H:i:s', (int)$articlePubDate));

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
