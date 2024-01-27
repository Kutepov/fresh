<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\ua\Telegraf\uk;

use common\components\scrapers\dto\ArticleItem;
use GuzzleHttp\Promise\Coroutine;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DomCrawler\Crawler;
use common\components\scrapers\common\Config;

/**
 * Class Scraper
 * @package common\components\scrapers\sources\ua\Telegraf\uk
 *
 * @Config (timezone="Europe/Kiev", urls={
 * "https://telegraf.com.ua/ukr/zhizn/",
 * "https://telegraf.com.ua/ukr/ukraina/politika/",
 * "https://telegraf.com.ua/ukr/ukraina/obshhestvo/",
 * "https://telegraf.com.ua/ukr/ukraina/mestnyiy/",
 * "https://telegraf.com.ua/ukr/mir/",
 * "https://telegraf.com.ua/ukr/kultura/",
 * "https://telegraf.com.ua/ukr/biznes/",
 * "https://telegraf.com.ua/ukr/sport-cat/",
 * "https://telegraf.com.ua/ukr/biznes/ekonomika/",
 * "https://telegraf.com.ua/ukr/auto/",
 * "https://telegraf.com.ua/ukr/nauka/"
 * })
 */
class Scraper extends \common\components\scrapers\sources\ua\Telegraf\ru\Scraper
{
    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addHtmlContent($pageContent->getBody()->getContents(), 'UTF-8');

            $selector = "//div[contains(@class, 'category-page__left_news_col')]";
            $articlesNode = $html->filterXPath($selector)->first();
            $articles = $articlesNode->filterXPath("//div[contains(@class, 'category-page__left') or contains(@class, 'category-page__center_news')]");

            $lastAddedPublicationTime = $this->lastPublicationTime;

            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {

                    $pageLinkNode = $node->filterXPath('//a');
                    if ($pageLinkNode->count()) {
                        $pageLink = $pageLinkNode->first()->attr('href');
                    }
                    else {
                        continue;
                    }

                    $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                    if ($this->isNeedSkipArticle($pageContent)) {
                        continue;
                    }
                    $html = new Crawler();
                    $html->addHtmlContent($pageContent->getBody()->getContents(), 'UTF-8');
                    $articleData = json_decode($html->filterXPath("//script[@type = 'application/ld+json']")->first()->text());

                    $articlePubDate = $articleData->datePublished;

                    if (!$articlePubDate) {
                        continue;
                    }

                    $publicationDate = $this->createDateFromString($articlePubDate);

                    if ($publicationDate->getTimestamp() > $lastAddedPublicationTime->getTimestamp()) {
                        $title = $node->filterXPath("//div[contains(@class, 'category-page__left_small_title')] | //div[@class='category-page__left_big_bottom']//a ")->first()->text();
                        $result[] = new ArticleItem($pageLink, $title, $publicationDate);
                    }
                } catch (\Throwable $exception) {
                    $this->logArticleItemException($exception);
                }
            }

            yield $result;
        });
    }

}