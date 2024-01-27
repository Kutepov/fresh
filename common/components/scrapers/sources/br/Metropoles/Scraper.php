<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\br\Metropoles;

use common\components\scrapers\common\ArticleBodyScraper;
use common\components\scrapers\common\helpers\PreviewHelper;
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
 * @package common\components\scrapers\sources\br\Metropoles;
 *
 * @Config (
 * timezone="America/Sao_Paulo", urls={
 * "https://www.metropoles.com/brasil",
 * "https://www.metropoles.com/celebridades",
 * "https://www.metropoles.com/concursos-e-empregos",
 * "https://www.metropoles.com/dino",
 * "https://www.metropoles.com/distrito-federal",
 * "https://www.metropoles.com/entretenimento",
 * "https://www.metropoles.com/esportes",
 * "https://www.metropoles.com/gastronomia",
 * "https://www.metropoles.com/mundo",
 * "https://www.metropoles.com/ultimas-noticias/",
 * "https://www.metropoles.com/sai-do-serio/",
 * "https://www.metropoles.com/saude/",
 * "https://www.metropoles.com/vida-e-estilo/",
 * "https://www.metropoles.com/violencia-contra-a-mulher/",
 * "https://www.metropoles.com/vitrine-m"
 * })
 */
class Scraper extends \common\components\scrapers\common\Scraper implements ArticleBodyScraper
{
    /**
     * @var PreviewHelper
     */
    private $previewHelper;

    /**
     * @var SelectorsRemover
     */
    private $selectorsRemover;

    /**
     * @var XPathParserV2
     */
    private $XPathParser;

    public function __construct(
        PreviewHelper $previewHelper,
        SelectorsRemover $selectorsRemover,
        XPathParserV2 $XPathParser,
        $config = []
    )
    {
        $this->previewHelper = $previewHelper;
        $this->selectorsRemover = $selectorsRemover;
        $this->XPathParser = $XPathParser;

        parent::__construct($config);
    }

    public function parseArticleBody(string $url): PromiseInterface
    {
        return $this->sendAsyncRequestWithProxy(new Request('GET', $url))->then(function (ResponseInterface $response) {
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents());
            return $this->XPathParser->parseDescription($html, '//*[@class="m-resume-single"]');
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//div[contains(@class, 'last-news')]";
            $articlesNode = $html->filterXPath($selector);
            $this->selectorsRemover->remove(
                "//div[contains(@class, 'itemAds')]",
                $articlesNode
            );
            $articles = $articlesNode->filterXPath("//article[contains(@class, 'm-feed-news-list')]");

            $lastAddedPublicationTime = $this->lastPublicationTime;
            $lastAddedPublicationTime = $lastAddedPublicationTime->setTimezone($this->timezone);
            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    $linkNode = $node->filterXPath("//h6[contains(@class, 'm-title')]//a");
                    $pageLink = $linkNode->attr('href');
                    $title = $linkNode->text();

                    $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                    if ($this->isNeedSkipArticle($pageContent)) {
                        continue;
                    }
                    $html = new Crawler();
                    $html->addHtmlContent($pageContent->getBody()->getContents(), 'UTF-8');

                    $articlePubDate = $html->filterXPath("//head//meta[@property='article:published_time']")->first();
                    $pubDateAttr = $articlePubDate->attr('content');
                    if (!$pubDateAttr) {
                        continue;
                    }

                    $publicationDate = $this->createDateFromString($pubDateAttr);
                    $hashPreview = $this->previewHelper->getOgImageUrlHash($html, 'compartilhamento_novo.png');

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
}
