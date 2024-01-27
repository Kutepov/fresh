<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\za\TheSouthAfrican;

use common\components\scrapers\common\helpers\PreviewHelper;
use common\components\scrapers\common\Config;
use common\components\scrapers\dto\ArticleItem;
use GuzzleHttp\Promise\Coroutine;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Class Scraper
 * @package common\components\scrapers\sources\za\TheSouthAfrican
 *
 * @Config (
 * timezone="Africa/Lusaka", urls={
 * "https://www.thesouthafrican.com/food/recipes/",
 * "https://www.thesouthafrican.com/lifestyle/",
 * "https://www.thesouthafrican.com/motoring/",
 * "https://www.thesouthafrican.com/news/",
 * "https://www.thesouthafrican.com/opinion/",
 * "https://www.thesouthafrican.com/sport/",
 * "https://www.thesouthafrican.com/technology/",
 * "https://www.thesouthafrican.com/travel/",
 * })
 */
class Scraper extends \common\components\scrapers\common\Scraper
{
    /**
     * @var PreviewHelper
     */
    private $previewHelper;

    public function __construct(
        PreviewHelper $previewHelper,
        $config = []
    )
    {
        $this->previewHelper = $previewHelper;

        parent::__construct($config);
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//div[contains(@id, '__next')]";
            $articlesNode = $html->filterXPath($selector);

            $articles = $articlesNode->filterXPath("//article[contains(@class, 'ArchiveList__EntryBoxStandardStyled-sc-1tzqen8-1')]");
            $lastAddedPublicationTime = $this->lastPublicationTime;

            $baseUrl = 'https://www.thesouthafrican.com';
            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    $linkNode = $node->filterXPath("//h3//a");
                    $pageLink = $baseUrl.$linkNode->attr('href');
                    $title = $linkNode->text();

                    $publicationDate = $this->createDateFromString($node->filterXPath("//span[contains(@class, 'Date__StyledDate-sc')]")->first()->text());
                    $imgHash = $this->previewHelper->getImageUrlHashFromList($node, "//img", 'data-src', $baseUrl);

                    if ($publicationDate >= $lastAddedPublicationTime) {
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
