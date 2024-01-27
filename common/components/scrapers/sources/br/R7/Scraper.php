<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\br\R7;

use common\components\scrapers\common\ArticleBodyScraper;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\XPathParserV2;
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
 * @package common\components\scrapers\sources\br\R7;
 *
 * @Config (
 * timezone="America/Sao_Paulo", urls={
 * "https://noticias.r7.com/saude/coronavirus",
 * "https://noticias.r7.com/",
 * "https://entretenimento.r7.com/",
 * "https://lifestyle.r7.com/",
 * "https://virtz.r7.com/",
 * "https://esportes.r7.com/"
 * })
 */
class Scraper extends \common\components\scrapers\common\Scraper implements ArticleBodyScraper
{
    /**
     * @var HashImageService
     */
    private $hashImageService;

    /**
     * @var XPathParserV2
     */
    private $XPathParser;

    public function __construct(
        HashImageService $hashImageService,
        XPathParserV2 $XPathParser,
        $config = []
    )
    {
        $this->hashImageService = $hashImageService;
        $this->XPathParser = $XPathParser;

        parent::__construct($config);
    }

    public function parseArticleBody(string $url): PromiseInterface
    {
        return $this->sendAsyncRequestWithProxy(new Request('GET', $url))->then(function (ResponseInterface $response) {
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents());
            return $this->XPathParser->parseDescription($html, '//div[@class="heading-title"]//h2');
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $urlToListArticles = $this->getUrlToListArticles($response->getBody()->getContents());

            $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $urlToListArticles));

            $listArticles = json_decode($pageContent->getBody()->getContents());

            $lastAddedPublicationTime = $this->lastPublicationTime;
            $lastAddedPublicationTime = $lastAddedPublicationTime->setTimezone($this->timezone);
            $result = [];
            foreach ($listArticles as $article) {

                try {
                    $pageLink = $article->url;
                    $pageLink = preg_replace('#^http://#', 'https://', $pageLink);
                    $title = $article->title;

                    $publicationDate = $this->createDateFromString($article->first_published_at);
                    $hashPreview = null;
                    if (isset($article->cover_image)) {
                        $hashPreview = @$this->hashImageService->hashImage($article->cover_image);
                    }

                    if ($publicationDate > $lastAddedPublicationTime) {
                        $result[] = new ArticleItem($pageLink, $title, $publicationDate, $hashPreview);
                    }
                }
                catch (\Throwable $exception) {
                    $this->logArticleItemException($exception, $pageLink);
                }
            }

            yield $result;
        });
    }

    /**
     * @param $html
     * @return false|string
     */
    public function getUrlToListArticles($html)
    {
        $content = strstr($html, 'urlArticle: \'');
        $content = strstr($content, "',", true);
        return strstr($content, "h");
    }
}
