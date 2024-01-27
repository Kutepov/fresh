<?php namespace common\components\scrapers\common;

use common\components\scrapers\common\services\HashImageService;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\ExternalServiceHelper\YoutubeHelper;
use common\components\scrapers\dto\ArticleBody;
use common\components\scrapers\dto\ArticleBodyNode;
use common\components\scrapers\dto\ArticleItem;
use GuzzleHttp\Promise\Coroutine;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use Symfony\Component\DomCrawler\Crawler;

class YoutubeRssScraper extends Scraper
{
    /**
     * @var HashImageService
     */
    private $hashImageService;

    /**
     * @var YoutubeHelper
     */
    private $youtubeHelper;

    public function __construct(
        HashImageService $hashImageService,
        YoutubeHelper    $youtubeHelper,
                         $config = []
    )
    {
        $this->hashImageService = $hashImageService;
        $this->youtubeHelper = $youtubeHelper;

        parent::__construct($config);
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//feed";
            $articlesNode = $html->filterXPath($selector);

            $articles = $articlesNode->filterXPath("//entry");

            $lastAddedPublicationTime = $this->lastPublicationTime;
            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    $linkNode = $node->filterXPath("//link");
                    $pageLink = $linkNode->attr('href');
                    $title = trim(removeEmoji($node->filterXPath('//title')->text()));

                    $articlePubDate = $node->filterXPath("//published")->text();
                    $publicationDate = $this->createDateFromString($articlePubDate);
                    $node->registerNamespace('media', $url);
                    $thumbnail = $node->filterXPath('//group//thumbnail')->attr('url');
//                    $thumbnail = preg_replace('#hqdefault\.jpg$#i', 'maxresdefault.jpg', $thumbnail);
                    $hashPreview = $this->hashImageService->hashImage($thumbnail);

                    if (!$lastAddedPublicationTime || $publicationDate > $lastAddedPublicationTime) {
                        $article = new ArticleItem($pageLink, $title, $publicationDate, $hashPreview);
                        $body = new ArticleBody();
                        $id = str_replace('yt:video:', '', $node->filterXPath('//id')->text());

                        $body->add(new ArticleBodyNode('video', $this->youtubeHelper->generateUrlForId($id)));
                        $article->setBody($body);

                        $result[] = $article;
                    }
                } catch (\Throwable $exception) {
                    $this->logArticleItemException($exception, $pageLink);
                }
            }
            yield $result;
        });
    }
}