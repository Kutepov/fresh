<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\br\Portaldoholanda;

use Carbon\Carbon;
use common\components\scrapers\common\ArticleBodyScraper;
use common\components\scrapers\common\helpers\PreviewHelper;
use common\components\scrapers\common\Config;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\XPathParserV2;
use common\components\scrapers\dto\ArticleItem;
use GuzzleHttp\Promise\Coroutine;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Class Scraper
 * @package common\components\scrapers\sources\br\Portaldoholanda;
 *
 * @Config (
 * timezone="America/Sao_Paulo", urls={
 * "https://www.portaldoholanda.com.br/bastidoresdapolitica",
 * "https://www.portaldoholanda.com.br/amazonas",
 * "https://www.portaldoholanda.com.br/policial",
 * "https://www.portaldoholanda.com.br/manaus",
 * "https://www.portaldoholanda.com.br/famosos-tv"
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
        PreviewHelper $previewHelper,
        XPathParserV2 $XPathParser,
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
            $html->addHtmlContent($response->getBody()->getContents());
            return $this->XPathParser->parseDescription($html, '//div[@class="corpo"]//p[1]');
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//div[@id='feed']";
            $articlesNode = $html->filterXPath($selector);

            $articles = $articlesNode->filterXPath("//div[contains(@class, 'columns')]");

            $lastAddedPublicationTime = $this->lastPublicationTime;
            $lastAddedPublicationTime = $lastAddedPublicationTime->setTimezone($this->timezone);
            $result = [];
            $baseUrl = 'https://www.portaldoholanda.com.br';
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    $linkNode = $node->filterXPath("//h3//a");
                    $pageLink = $linkNode->attr('href');
                    $title = $linkNode->text();

                    if (!filter_var($pageLink, FILTER_VALIDATE_URL)) {
                        $pageLink = $baseUrl . $pageLink;
                    }

                    $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                    if ($this->isNeedSkipArticle($pageContent)) {
                        continue;
                    }
                    $html = new Crawler();
                    $html->addHtmlContent($pageContent->getBody()->getContents(), 'UTF-8');

                    $articlePubDate = $html->filterXPath("//p[@class='destaque ha']")->first();

                    $publicationDate = $this->parseDate($articlePubDate->text());

                    $hashPreview = $this->previewHelper->getOgImageUrlHash($html);

                    if ($publicationDate > $lastAddedPublicationTime) {
                        $result[] = new ArticleItem($pageLink, $title, $publicationDate, $hashPreview);
                    }
                } catch (\Throwable $exception) {
                    $this->logArticleItemException($exception, $pageLink);
                }
            }

            yield $result;
        });
    }

    /**
     * @param string $string
     * @return Carbon
     * @throws \Exception
     */
    private function parseDate(string $string): Carbon
    {
        [$date, $time] = explode(' ', $string);
        $time = str_replace('h', ':', $time);
        $date = str_replace('/', '.', $date);
        return $this->createDateFromString($date . ' ' . $time);
    }
}
