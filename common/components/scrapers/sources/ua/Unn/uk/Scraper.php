<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\ua\Unn\uk;

use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\BaseUrls;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\XPathParserV2;
use common\components\scrapers\common\services\HashImageService;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\NewsCutter;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\SelectorsRemover;
use common\components\scrapers\common\ArticleBodyScraper;
use common\components\scrapers\dto\ArticleBody;
use common\components\scrapers\dto\ArticleBodyNode;
use common\components\scrapers\dto\ArticleBodyWithDescription;
use common\components\scrapers\dto\ArticleItem;
use common\models\Article;
use GuzzleHttp\Promise\Coroutine;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DomCrawler\Crawler;
use common\components\scrapers\common\Config;

/**
 * Class Scraper
 * @package common\components\scrapers\sources\ua\Unn\uk
 *
 * @Config (timezone="Europe/Kiev", urls={
"https://www.unn.com.ua/uk/news/agronews",
"https://www.unn.com.ua/uk/news/criminal",
"https://www.unn.com.ua/uk/news/culture",
"https://www.unn.com.ua/uk/news/economics",
"https://www.unn.com.ua/uk/news/health",
"https://www.unn.com.ua/uk/news/kiev",
"https://www.unn.com.ua/uk/news/lite",
"https://www.unn.com.ua/uk/news/politics",
"https://www.unn.com.ua/uk/news/society",
"https://www.unn.com.ua/uk/news/sport",
"https://www.unn.com.ua/uk/news/tech",
"https://www.unn.com.ua/uk/news/world"
 * })
 */
class Scraper extends \common\components\scrapers\common\Scraper implements ArticleBodyScraper
{
    const REPLACE_TAGS = [
        'p' => [
            [
                'contains' => 'twitter',
                'attribute' => 'href',
                'valueType' => 'href',
                'elementName' => 'twitter',
            ]
        ],
        'script' => [
            [
                'attribute' => 'data-telegram-post',
                'valueType' => 'data-telegram-post',
                'elementName' => 'telegram',
            ],
        ]
    ];
    /**
     * @var SelectorsRemover
     */
    private $selectorsRemover;

    /**
     * @var HashImageService
     */
    private $hashImageService;

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
        HashImageService $hashImageService,
        NewsCutter $newsCutter,
        XPathParserV2 $XPathParser,
        $config = []
    )
    {
        $this->selectorsRemover = $selectorsRemover;
        $this->hashImageService = $hashImageService;
        $this->XPathParser = $XPathParser;
        $this->newsCutter = $newsCutter;

        parent::__construct($config);
    }

    public function parseArticleBody(string $url): PromiseInterface
    {
        return $this->sendAsyncRequestWithProxy(new Request('GET', $url))->then(function (ResponseInterface $response) {

            $pageContent = $response->getBody()->getContents();

            $html = new Crawler();
            $html->addHtmlContent($pageContent, 'UTF-8');


            $selector = '//div[contains(@class, "b-news-holder")]';

            $textNode = $html->filterXPath($selector)->first();

            $gallery = $this->parseGallery($textNode->filterXPath("//ul[@class='slides']//a"));

            $this->selectorsRemover->remove(
                '//strong[contains(text(), "ЧИТАЙТЕ ТАКОЖ")]|
                               //p[@dir="ltr"]|
                               //a[contains(@href, "instagram.com")]|
                               //ul[@class="slides"]',

                $textNode
            );

            $newsLinks = $textNode->filterXPath("//div[contains(@class, 'b-news-text')]//a");
            $this->newsCutter->cutterByLinks($newsLinks);

            $text = $textNode->filterXPath(
                "//div[contains(@class, 'b-news-full-img')]//img|
//div[contains(@class, 'b-news-text')]//p|
//div[contains(@class, 'b-news-text')]//img|
//div[contains(@class, 'b-news-text')]//iframe[contains(@src, 'youtube.com')]|
//div[contains(@class, 'b-news-text')]//iframe[contains(@src, 'facebook.com/plugins/video.php')]|
//div[contains(@class, 'b-news-text')]//blockquote[not(contains(@class, 'twitter-tweet'))]|
//div[contains(@class, 'b-news-text')]//ul|
//div[contains(@class, 'b-news-text')]//video/source|
//div[contains(@class, 'flowplayer')]//video/source|
//div[contains(@class, 'flowplayer')]//video/source|
//div[contains(@class, 'b-news-text')]//a|
//div[contains(@class, 'b-news-text')]//ol|
//div[contains(@class, 'b-news-text')]//script"
            );

            $baseUrls = new BaseUrls();
            $baseUrls->addImageUrl('https://www.unn.com.ua/');
            $baseUrls->addVideoUrl('https://www.unn.com.ua/');

            $result = $this->XPathParser->parse($text, self::REPLACE_TAGS, $baseUrls);

            $newBody = new ArticleBodyWithDescription();
            $ul = [];
            foreach ($result->getNodes() as $node) {
                if (
                    $node->getElementName() === Article::BODY_PART_PARAGRAPH &&
                    preg_match_all('#▪️ (.*?)(;\s|\.|$)#su', $node->getValue(), $ulMatch, PREG_SET_ORDER)
                ) {
                    $ul = array_merge($ul, array_map(static function ($li) {
                        return trim($li[1]);
                    }, $ulMatch));
                }
                else {
                    if (count($ul)) {
                        $newBody->add(new ArticleBodyNode(Article::BODY_PART_UL, $ul));
                        $ul = [];
                    }
                    $newBody->add($node);
                }
            }

            if ($gallery) {
                $newBody->add($gallery);
            }

            $description = $this->XPathParser->parseDescription($html, '//div[@itemprop="articleBody""]//p[1]')->getNodes()[0]->getValue();
            $newBody->setDescription($description);

            return $newBody;
        });
    }

    public function parseGallery(Crawler $node): ?ArticleBodyNode
    {
        if ($node->count()) {
            $self = $this;
            $node->each(
                function (Crawler $node) use (&$images, $self) {
                    $imgSrc = $node->attr('href');
                    if ($imgSrc) {
                        $scheme = parse_url($imgSrc, PHP_URL_SCHEME);
                        if (!$scheme) {
                            $imgSrc = 'https://www.unn.com.ua' . $imgSrc;
                        }
                        $images[] = $self->hashImageService->hashImage($imgSrc);
                    }
                    else {
                        return;
                    }
                }
            );

            return new ArticleBodyNode('carousel', $images);


        } else {
            return null;
        }
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addHtmlContent($pageContent->getBody()->getContents(), 'UTF-8');

            $selector = "//div[@class='h-news-feed']";
            $articlesNode = $html->filterXPath($selector)->first();

            $articles = $articlesNode->filterXPath("//li");

            $lastAddedPublicationTime = $this->lastPublicationTime;

            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    $linkNode = $node->filterXPath('//a')->first();
                    $pageLink = 'https://www.unn.com.ua' . $linkNode->attr('href');
                    $title = $linkNode->text();


                    $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                    if ($this->isNeedSkipArticle($pageContent)) {
                        continue;
                    }
                    $html = new Crawler();
                    $html->addHtmlContent($pageContent->getBody()->getContents(), 'UTF-8');

                    $articleData = $html->filterXPath("//span[@class = 'datelink']")->first()->attr('content');

                    if (!$articleData) {
                        continue;
                    }

                    $publicationDate = $this->createDateFromString($articleData);

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