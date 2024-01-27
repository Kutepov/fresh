<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\ua\UaNews\uk;

use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\BaseUrls;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\XPathParserV2;
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
 * @package common\components\scrapers\sources\ua\UaNews\uk
 *
 * @Config (timezone="Europe/Kiev", urls={
 * "https://ua.news/ua/world/",
 * "https://ua.news/ua/capital/",
 * "https://ua.news/ua/technologies/",
 * "https://ua.news/ua/money/",
 * "https://ua.news/ua/life/",
 * "https://ua.news/ua/sport/",
 * "https://ua.news/ua/interview/"
 * })
 */
class Scraper extends \common\components\scrapers\common\Scraper implements ArticleBodyScraper
{

    private const OVERRIDE_REPLACE_TAGS = [
        'iframe' => [
            [
                'contains' => '1plus1',
                'attribute' => 'src',
                'valueType' => 'video',
                'elementName' => 'video',
            ],
        ],
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
     * @var BaseUrls
     */
    private $BaseUrls;

    /**
     * @var NewsCutter
     */
    private $newsCutter;

    public function __construct(
        SelectorsRemover $selectorsRemover,
        NewsCutter $newsCutter,
        XPathParserV2 $XPathParser,
        BaseUrls $baseUrls,
        $config = []
    )
    {
        $this->selectorsRemover = $selectorsRemover;
        $this->XPathParser = $XPathParser;
        $this->newsCutter = $newsCutter;
        $this->BaseUrls = $baseUrls;

        parent::__construct($config);
    }

    public function parseArticleBody(string $url): PromiseInterface
    {
        return $this->sendAsyncRequestWithProxy(new Request('GET', $url))->then(function (ResponseInterface $response) {

            $pageContent = $response->getBody()->getContents();
            $raplaceTags = self::OVERRIDE_REPLACE_TAGS;

            $html = new Crawler();
            $html->addHtmlContent($pageContent, 'UTF-8');


            $selector = '//div[contains(@class, "content-with-sidebar")]';

            $textNode = $html->filterXPath($selector)->first();

            $this->selectorsRemover->remove(
                '//div[contains(@class, "single-info")]|//div[contains(@class, "quote-with-img__info")]',
                $textNode
            );

            $newsLinks = $textNode->filterXPath("//div[contains(@itemprop, 'articleBody')]//a");
            $this->newsCutter->cutterByLinks($newsLinks);

            $text = $textNode->filterXPath(
                <<<XPATH
//div[contains(@class, 'content-with-sidebar')]/div[contains(@itemprop, 'articleBody')]/p|
//div[contains(@class, 'content-with-sidebar')]/div[contains(@itemprop, 'articleBody')]//img|
//img[contains(@class, 'cover-photo')]|//div[contains(@class, 'content-with-sidebar')]/div[contains(@itemprop, 'articleBody')]//blockquote
XPATH
            );

            $this->BaseUrls->addImageUrl('https://ua.news');

            $result = $this->XPathParser->parse($text, $raplaceTags, $this->BaseUrls, false, null, true, true);

            $description = $this->XPathParser->parseDescription($html, '//div[@itemprop="articleBody]//p[1]')->getNodes()[0]->getValue();
            $result->setDescription($description);

            return $result;
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addHtmlContent($pageContent->getBody()->getContents(), 'UTF-8');

            $selector = "//section[@class='archive__content']//div[@class='blog-loop archive__articles__holder']";
            $articlesNode = $html->filterXPath($selector)->first();

            $articles = $articlesNode->filterXPath("//article");

            $lastAddedPublicationTime = $this->lastPublicationTime;

            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {

                    $linkNode = $node->filterXPath('//a')->first();
                    $pageLink = $linkNode->attr('href');
                    $title = $node->filterXPath('//h3')->first()->text();

                    $articlePubDate = $node->filterXPath("//time")->first()->text();

                    if (!$articlePubDate) {
                        continue;
                    }
                    $publicationDate = $this->createDateFromString($articlePubDate);

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