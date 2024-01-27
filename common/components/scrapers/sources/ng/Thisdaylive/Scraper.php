<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\ng\Thisdaylive;

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
 * @package common\components\scrapers\sources\ng\Thisdaylive;
 *
 * @Config (
 * timezone="Africa/Lusaka", urls={
 * "https://www.thisdaylive.com/index.php/backpage/",
 * "https://www.thisdaylive.com/index.php/business/",
 * "https://www.thisdaylive.com/index.php/editorial/",
 * "https://www.thisdaylive.com/index.php/education/",
 * "https://www.thisdaylive.com/index.php/health-wellbeing/",
 * "https://www.thisdaylive.com/index.php/life_and_style/",
 * "https://www.thisdaylive.com/index.php/nigeria/",
 * "https://www.thisdaylive.com/index.php/politics/",
 * "https://www.thisdaylive.com/index.php/sports/"
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
            return $this->XPathParser->parseDescription($html, '//article//p[1]');
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//div[contains(@class, 'td_block_inner')]";
            $articlesNode = $html->filterXPath($selector);
            $articles = $articlesNode->filterXPath("//div[contains(@class, 'td-block-row')]//div[contains(@class, 'td-block-span')]");

            $lastAddedPublicationTime = $this->lastPublicationTime;

            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    $linkNode = $node->filterXPath("//div[contains(@class, 'item-details')]//h3[contains(@class, 'td-module-title')]//a")->first();
                    if (!$linkNode->count()) {
                        continue;
                    }
                    $pageLink = $linkNode->attr('href');
                    $title = $linkNode->text();
                    if (!$title) {
                        continue;
                    }

                    $articlePubDate = $node->filterXPath("//span[contains(@class, 'td-post-date')]//time")->first();
                    $pubDateAttr = $articlePubDate->attr('datetime');
                    if (!$pubDateAttr) {
                        continue;
                    }
                    $publicationDate = $this->createDateFromString($pubDateAttr);
                    $imgHash = $this->previewHelper->getImageUrlHashFromList($node);

                    if ($publicationDate > $lastAddedPublicationTime) {
                        $result[] = new ArticleItem($pageLink, $title, $publicationDate, $imgHash);
                    }
                } catch (\Throwable $exception) {
                    $this->logArticleItemException($exception, $pageLink);
                }
            }

            yield $result;
        });
    }
}
