<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\ua\Korrespondent\uk;

use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\XPathParserV2;
use common\components\scrapers\common\services\HashImageService;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\NewsCutter;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\SelectorsRemover;
use common\components\scrapers\common\ArticleBodyScraper;
use common\components\scrapers\dto\ArticleBody;
use common\components\scrapers\dto\ArticleBodyNode;
use common\components\scrapers\dto\ArticleItem;
use GuzzleHttp\Promise\Coroutine;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use Symfony\Component\DomCrawler\Crawler;
use common\components\scrapers\common\Config;

/**
 * Class Scraper
 * @package common\components\scrapers\sources\ua\Korrespondent\uk
 *
 * @Config (timezone="Europe/Kiev", urls={
    "https://ua.korrespondent.net/business/auto/",
    "https://ua.korrespondent.net/business/economics/",
    "https://ua.korrespondent.net/city/",
    "https://ua.korrespondent.net/showbiz/",
    "https://ua.korrespondent.net/sport/",
    "https://ua.korrespondent.net/tech/medicine/",
    "https://ua.korrespondent.net/tech/science/",
    "https://ua.korrespondent.net/ukraine/politics/",
    "https://ua.korrespondent.net/world/",
 * })
 */
class Scraper extends \common\components\scrapers\common\Scraper implements ArticleBodyScraper
{

    private const OVERRIDE_REPLACE_TAGS = [
        'a' => [
            [
                'contains' => 'twitter.com',
                'attribute' => 'src',
                'valueType' => 'href',
                'elementName' => 'twitter',
            ],
            [
                'contains' => 'gallery',
                'attribute' => 'name',
                'valueType' => 'name',
                'elementName' => 'gallery',
            ]
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

    /**
     * @var HashImageService
     */
    private $hashImageService;


    public function __construct(
        SelectorsRemover $selectorsRemover,
        NewsCutter $newsCutter,
        XPathParserV2 $XPathParser,
        HashImageService $hashImageService,
        $config = []
    )
    {
        $this->hashImageService = $hashImageService;
        $this->selectorsRemover = $selectorsRemover;
        $this->XPathParser = $XPathParser;
        $this->newsCutter = $newsCutter;

        parent::__construct($config);
    }

    public function parseArticleBody(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));

            $pageContent = $response->getBody()->getContents();
            $raplaceTags = self::OVERRIDE_REPLACE_TAGS;

            $html = new Crawler();
            $html->addHtmlContent($pageContent, 'UTF-8');


            $selector = '//div[@class="col__big"]';

            $textNode = $html->filterXPath($selector)->first();


            $this->selectorsRemover->remove(
                '//div[contains(@class, "post-item__info")]|
                              //div[@class="post-item__header clearfix"]|
                              //div[@class="post-item__tags clearfix"]|
                              //div[@class="post-item__photo-about"]|
                              //noscript|
                              //img[contains(@src, "blank")]|
                              //span[contains(text(), "ЧИТАЙТЕ ТАК")]|
                              //p[contains(string(), "Подписывайтесь на наш канал")]|
                              //*[contains(text(), "Новини від")]|
                              //script[not(contains(@src, "https://telegram.org/js/telegram-widget"))]|
                              //div[@class="subpartition-partnership"]|
                              //span[@style="color:#ff0000;"]
                ', $textNode
            );

            $newsLinks = $textNode->filterXPath('//a');
            $this->newsCutter->cutterByLinks($newsLinks);

            $text = $textNode->filterXPath(
                '//img|
                        //p|
                        //h2|
                        //blockquote[not(@class="twitter-tweet")]|
                        //ul|
                        //div[@class="post-item__text"]/div|
                        //iframe[contains(@src, "youtube.com")]|
                        //div[@id="insertGalleryBlock"]//a|
                        //script[@data-telegram-post]'
            );

            $articleBody = $this->XPathParser->parse($text, $raplaceTags, null, false, null, true, true);

            $gallery = $textNode->filterXPath("//div[@id='insertGalleryBlock']//a")->first();
            if ($gallery->count() > 0) {
                $id = str_replace('gallery', '', $gallery->attr('name'));
                $gallery = yield $this->parseGallery($id);

                $articleBodyUpdate = new ArticleBody();

                foreach ($articleBody->getNodes() as $item) {
                    if ($item->getElementName() === 'gallery') {
                        $articleBodyUpdate->add($gallery);
                    }
                    else {
                        $articleBodyUpdate->add($item);
                    }
                }

                return yield $articleBodyUpdate;

            }

            $description = $this->XPathParser->parseDescription($html, '//div[@class="post-item__text"]//h2|//div[@class="a_card"]//div[1]')->getNodes()[0]->getValue();
            $articleBody->setDescription($description);

            return yield $articleBody;
        });
    }

    private function parseGallery($id)
    {
        return Coroutine::of(function () use ($id) {
            $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', 'https://ua.korrespondent.net/ajax/module.aspx?spm_id=520&id='.$id.'&lang=2&IsAjax=true'));
            $html = new Crawler();
            $html->addHtmlContent($pageContent->getBody()->getContents(), 'UTF-8');
            $images = [];
            $imagesNodes = $html->filterXPath("//a[@class='rsImg']");
            $imagesNodes->each(function (Crawler $node) use (&$images) {
                $images[] = $this->hashImageService->hashImage($node->attr('data-rsbigimg'));
            });
            yield new ArticleBodyNode('carousel', $images);
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addHtmlContent($pageContent->getBody()->getContents(), 'UTF-8');

            $selector = "//div[@class='col__main partition' or @class='col__big subpartition']";
            $articlesNode = $html->filterXPath($selector)->first();

            $articles = $articlesNode->filterXPath("//div[@class='article']|//div[@class='article article_rubric_top']|//div[@class='article article_top']");

            $lastAddedPublicationTime = $this->lastPublicationTime;

            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    $linkNode = $node->filterXPath('//div[contains(@class, "article__title")]//a')->first();

                    $em = $linkNode->filterXPath('//em')->first();

                    if ($em->count()) {
                        $this->selectorsRemover->remove('//em', $linkNode);
                        if ($em->text() === 'Реклама') {
                            continue;
                        }
                    }

                    $pageLink = $linkNode->attr('href');
                    $title = $linkNode->text();

                    $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                    if ($this->isNeedSkipArticle($pageContent)) {
                        continue;
                    }
                    $html = new Crawler();
                    $html->addHtmlContent($pageContent->getBody()->getContents(), 'UTF-8');

                    $scriptNode = $html->filterXPath("//script[@type = 'application/ld+json']")->eq(1)->first();

                    if (!$scriptNode->count()) {
                        continue;
                    }

                    $articleData = json_decode($scriptNode->text());

                    $articlePubDate = $articleData->datePublished;

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