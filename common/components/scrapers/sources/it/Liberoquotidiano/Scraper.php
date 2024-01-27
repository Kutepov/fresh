<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\it\Liberoquotidiano;

use common\components\scrapers\common\ArticleBodyScraper;
use common\components\scrapers\common\Config;
use common\components\scrapers\common\helpers\PreviewHelper;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\XPathParserV2;
use common\components\scrapers\dto\ArticleItem;
use GuzzleHttp\Promise\Coroutine;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Class Scraper
 * @package common\components\scrapers\sources\it\Liberoquotidiano;
 *
 * @Config (
 * timezone="Europe/Paris", urls={
 * "https://www.liberoquotidiano.it/politica/",
 * "https://www.liberoquotidiano.it/politica/rissa-politica/",
 * "https://www.liberoquotidiano.it/italia/",
 * "https://www.liberoquotidiano.it/esteri/",
 * "https://www.liberoquotidiano.it/economia/",
 * "https://www.liberoquotidiano.it/spettacoli/",
 * "https://www.liberoquotidiano.it/sport/",
 * "https://www.liberoquotidiano.it/milano/",
 * "https://www.liberoquotidiano.it/scienze-tech/alimentazione-e-benessere/",
 * "https://www.liberoquotidiano.it/scienze-tech/salute/"
 * })
 */
class Scraper extends \common\components\scrapers\common\Scraper implements ArticleBodyScraper
{

    /**
     * @var PreviewHelper
     */
    private $previewHelper;

    /**
     * @var XPathParserV2
     */
    private $XPathParser;

    public function __construct(
        XPathParserV2 $XPathParser,
        PreviewHelper $previewHelper,
                      $config = []
    )
    {
        $this->previewHelper = $previewHelper;
        $this->XPathParser = $XPathParser;

        parent::__construct($config);
    }

    public function parseArticleBody(string $url): PromiseInterface
    {
        return $this->sendAsyncRequestWithProxy(new Request('GET', $url))->then(function (ResponseInterface $response) {
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');
            return $this->XPathParser->parseDescription($html);
        });
    }


    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//main";
            $articlesNode = $html->filterXPath($selector);

            $articles = $articlesNode->filterXPath("//article[contains(@class, 'box')]");

            $lastAddedPublicationTime = $this->lastPublicationTime;
            $lastAddedPublicationTime = $lastAddedPublicationTime->setTimezone($this->timezone);
            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    $linkNode = $node->filterXPath("//a");
                    $pageLink = $linkNode->attr('href');

                    $title = $node->filterXPath("//h2")->first();

                    if (!$title->count()) {
                        continue;
                    }

                    $title = $title->text();

                    $dataArticle = $node->filterXPath("//time")->first();

                    if (!$dataArticle->count()) {
                        continue;
                    }

                    $dataArticle = $dataArticle->text();

                    $publicationDate = $this->createDateFromString(str_replace('/', '.', $dataArticle));

                    $hashPreview = $this->previewHelper->getImageUrlHashFromList($node, "//img", "data-src");

                    if ($publicationDate >= $lastAddedPublicationTime) {
                        $result[] = new ArticleItem($pageLink, $title, $publicationDate, $hashPreview);
                    }
                } catch (\Throwable $exception) {
                    $this->logArticleItemException($exception, $pageLink);
                }

            }

            yield $result;
        });
    }
}
