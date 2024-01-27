<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\gb\Mirror;

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
 * @package common\components\scrapers\sources\gb\Mirror;
 *
 * @Config (
 * timezone="Europe/London", urls={
 * "https://www.mirror.co.uk/all-about/betting",
 * "https://www.mirror.co.uk/play/competitions/",
 * "https://www.mirror.co.uk/all-about/crime",
 * "https://www.mirror.co.uk/lifestyle/family/",
 * "https://www.mirror.co.uk/lifestyle/health/",
 * "https://www.mirror.co.uk/money/",
 * "https://www.mirror.co.uk/lifestyle/motoring/",
 * "https://www.mirror.co.uk/news/politics/",
 * "https://www.mirror.co.uk/play/quizzes/",
 * "https://www.mirror.co.uk/news/real-life-stories/",
 * "https://www.mirror.co.uk/science/",
 * "https://www.mirror.co.uk/sport/",
 * "https://www.mirror.co.uk/3am/style/",
 * "https://www.mirror.co.uk/tech/",
 * "https://www.mirror.co.uk/travel/",
 * "https://www.mirror.co.uk/tv/",
 * "https://www.mirror.co.uk/news/uk-news/",
 * "https://www.mirror.co.uk/news/us-news/",
 * "https://www.mirror.co.uk/news/weird-news/",
 * "https://www.mirror.co.uk/news/world-news/"
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
            return $this->XPathParser->parseDescription($html, '//p[@itemprop="description"]');
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//main[contains(@class, 'mod-pancakes')]";
            $articlesNode = $html->filterXPath($selector);
            $articles = $articlesNode->filterXPath("//article");

            $lastAddedPublicationTime = $this->lastPublicationTime;
            $lastAddedPublicationTime = $lastAddedPublicationTime->setTimezone($this->timezone);
            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    $linkNode = $node->filterXPath("//a");
                    if (!$linkNode->count()) {
                        continue;
                    }
                    $pageLink = $linkNode->attr('href');
                    $title = $linkNode->text();

                    $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                    if ($this->isNeedSkipArticle($pageContent)) {
                        continue;
                    }
                    $html = new Crawler();
                    $html->addHtmlContent($pageContent->getBody()->getContents(), 'UTF-8');

                    $articlePubDate = $html->filterXPath("//ul[contains(@class, 'time-info')]//time")->first();
                    $pubDateAttr = $articlePubDate->attr('datetime');
                    if (!$pubDateAttr) {
                        continue;
                    }
                    $publicationDate = $this->createDateFromString($pubDateAttr);

                    $hashPreview = $this->previewHelper->getOgImageUrlHash($html);

                    if ($publicationDate > $lastAddedPublicationTime) {
                        $immutableDate = \DateTimeImmutable::createFromMutable($publicationDate);

                        $result[] = new ArticleItem($pageLink, $title, $immutableDate, $hashPreview);
                    }
                } catch (\Throwable $exception) {
                    $this->logArticleItemException($exception, $pageLink);
                }

            }

            yield $result;
        });
    }
}
