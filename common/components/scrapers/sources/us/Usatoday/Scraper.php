<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\us\Usatoday;

use common\components\scrapers\common\helpers\PreviewHelper;
use common\components\scrapers\dto\ArticleItem;
use GuzzleHttp\Promise\Coroutine;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DomCrawler\Crawler;
use common\components\scrapers\common\Config;

/**
 * Class Scraper
 * @package common\components\scrapers\sources\us\Usatoday
 *
 * @Config (timezone="America/New_York", urls={
 * "https://www.usatoday.com/money/",
 * "https://www.usatoday.com/travel/",
 * "https://www.usatoday.com/tech/",
 * "https://www.usatoday.com/sports/",
 * "https://www.usatoday.com/life/"
 * })
 */
class Scraper extends \common\components\scrapers\common\Scraper
{

    private const MONTHS = [
        'Jan' => 1,
        'Feb' => 2,
        'Mar' => 3,
        'Apr' => 4,
        'May' => 5,
        'Jun' => 6,
        'Jul' => 7,
        'Aug' => 8,
        'Sep' => 9,
        'Oct' => 10,
        'Nov' => 11,
        'Dec' => 12,
        'Jan.' => 1,
        'Feb.' => 2,
        'Mar.' => 3,
        'Apr.' => 4,
        'May.' => 5,
        'Jun.' => 6,
        'Jul.' => 7,
        'Aug.' => 8,
        'Sep.' => 9,
        'Oct.' => 10,
        'Nov.' => 11,
        'Dec.' => 12,
    ];


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
            $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler($pageContent->getBody()->getContents());

            $selector = "//main[@class='gnt_cw']";
            $articlesNode = $html->filterXPath($selector)->first();

            $articles = $articlesNode->filterXPath("//a[@class='gnt_m_he']|//a[@class='gnt_m_tl']|//a[@class='gnt_m_flm_a']|//a[@class='gnt_m_th_a']");
            $lastAddedPublicationTime = $this->lastPublicationTime;
            $baseUrl = 'https://www.usatoday.com';
            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    $pageLink = $node->attr('href');
                    if (!filter_var($pageLink, FILTER_VALIDATE_URL)) {
                        $pageLink = $baseUrl . $node->attr('href');
                    }
                    if (!filter_var($pageLink, FILTER_VALIDATE_URL)) {
                        continue;
                    }

                    $title = $node->text();

                    $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                    if ($this->isNeedSkipArticle($pageContent)) {
                        continue;
                    }
                    $html = new Crawler($pageContent->getBody()->getContents());

                    $classDescription = $html->filterXPath("//div[@class='description']")->first();

                    if ($classDescription->count() && $classDescription->text() === 'A collection of articles to help you manage your finances like a pro.') {
                        continue;
                    }

                    $dateNode = $node->filterXPath("//div[contains(@class, 'gnt_sbt__ts')]")->first();

                    if (!$dateNode->count()) {
                        continue;
                    }

                    $publicationDate = $this->prepareDate($dateNode->attr('data-c-dt'));

                    $hashPreview = $this->previewHelper->getImageUrlHashFromList($node, "//img", ['src', 'data-gl-src']);

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

    private function prepareDate($string)
    {
        if (stripos($string, ',')) {
            [$date, $year] = explode(',', $string);
            [$month, $day] = explode(' ', $date);
            return $this->createDateFromString($day . '-' . self::MONTHS[trim($month, ' .')] . '-' . trim($year));
        }
        else {
            [$time, $date] = explode(' ET ', $string);
            [$month, $day] = explode(' ', $date);
            return $this->createDateFromString($day . '-' . self::MONTHS[trim($month, ' .')] . '-' . date('Y') . ' ' . $time);
        }
    }
}