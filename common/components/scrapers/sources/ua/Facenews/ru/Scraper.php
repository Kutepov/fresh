<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\ua\Facenews\ru;

use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\BaseUrls;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\XPathParserV2;
use common\components\scrapers\common\services\HashImageService;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\NewsCutter;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\SelectorsRemover;
use common\components\scrapers\common\ArticleBodyScraper;
use common\components\scrapers\dto\ArticleBodyNode;
use common\components\scrapers\dto\ArticleItem;
use GuzzleHttp\Promise\Coroutine;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DomCrawler\Crawler;
use common\components\scrapers\common\Config;

/**
 * Class Scraper
 * @package common\components\scrapers\sources\ua\Facenews\uk
 *
 * @Config (timezone="Europe/Kiev", urls={
 * "https://www.facenews.ua/news/"
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
     * @var HashImageService
     */
    private $hashImageService;

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
        HashImageService $hashImageService,
        NewsCutter $newsCutter,
        XPathParserV2 $XPathParser,
        BaseUrls $baseUrls,
        $config = []
    )
    {
        $this->selectorsRemover = $selectorsRemover;
        $this->hashImageService = $hashImageService;
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


            $selector = '//article';

            $textNode = $html->filterXPath($selector)->first();

            $carousel = $textNode->filterXPath('//div[@class="fotorama slideshow"]')->first();

            $this->selectorsRemover->remove(
                '//*[contains(@class, "readmore")]|
                                //p/small|
                                //div[contains(@class, "fotorama slideshow")]//img
                ', $textNode
            );

            $newsLinks = $textNode->filterXPath('//a');
            $this->newsCutter->cutterByLinks($newsLinks);

            $text = $textNode->filterXPath(
                '//img|
                        //p[not(blockquote[contains(@class, "twitter-tweet")])]|
                        //iframe[contains(@src, "youtube.com")]|
                        //iframe[contains(@src, "instagram.com")]|
                        //iframe[contains(@src, "t.me")]|
                        //iframe[contains(@src, "1plus1")]|
                        //blockquote|
                        //ul|
                        //iframe[contains(@src, "facebook.com")]'
            );


            $this->BaseUrls->addImageUrl('https://www.facenews.ua');

            $articleBody = $this->XPathParser->parse($text, $raplaceTags, $this->BaseUrls, false, null, true, true);

            if ($carousel->count()) {
                $articleBody->add($this->parseGallery($carousel));
            }

            $description = $this->XPathParser->parseDescription($html, '//article//p[1]')->getNodes()[0]->getValue();
            $articleBody->setDescription($description);

            return $articleBody;
        });
    }

    public function parseGallery(Crawler $carouselNode): ?ArticleBodyNode
    {
        try {
            $imagesNodes = $carouselNode->filterXPath('//a');
            $images = [];
            if ($imagesNodes->count()) {
                $self = $this;
                $imagesNodes->each(
                    function (Crawler $node) use (&$images, $self) {
                        $imgSrc = $node->attr('href');
                        if ($imgSrc) {
                            $images[] = $self->hashImageService->hashImage($imgSrc);
                        } else {
                            return;
                        }
                    }
                );

                return new ArticleBodyNode('carousel', $images);
            } else {
                return null;
            }
        } catch (\Throwable $exception) {
            return null;
        }

    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addHtmlContent($pageContent->getBody()->getContents(), 'UTF-8');

            $selector = "//div[contains(@class, 'main')]//div[@class='container']//div[@class='row']//div[@class='col-sm-6 col-md-8']";
            $articlesNode = $html->filterXPath($selector)->first();

            $articles = $articlesNode->filterXPath("//div[@class='item inline']");

            $lastAddedPublicationTime = $this->lastPublicationTime;

            $baseUrl = 'https://www.facenews.ua';

            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {

                    $linkNode = $node->filterXPath('//a')->first();
                    $pageLink = $baseUrl.$linkNode->attr('href');
                    $title = $linkNode->text();

                    $articlePubDate = $node->filterXPath("//div[@class='item_info']//div[@class='item_datetime']")->first()->text();

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