<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\ua\Gordon\ru;

use Carbon\Carbon;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\BaseUrls;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\XPathParserV2;
use common\components\scrapers\dto\ArticleBodyNode;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\ExternalServiceHelper\InstagramHelper;
use common\components\scrapers\common\services\HashImageService;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\NewsCutter;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\SelectorsRemover;
use common\components\scrapers\common\ArticleBodyScraper;
use common\components\scrapers\dto\ArticleBody;
use common\components\scrapers\dto\ArticleItem;
use GuzzleHttp\Promise\Coroutine;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DomCrawler\Crawler;
use common\components\scrapers\common\Config;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\ExternalServiceHelper\YoutubeHelper;

/**
 * Class Scraper
 * @package common\components\scrapers\sources\ua\Gordon\ru
 *
 * @Config (timezone="Europe/Kiev", urls={
"https://gordonua.com/ukr/bulvar.html",
"https://gordonua.com/ukr/interview.html",
 * "https://gordonua.com/news.html",
 * "https://gordonua.com/news/culture.html",
 * "https://gordonua.com/news/health.html",
 * "https://gordonua.com/news/money.html",
 * "https://gordonua.com/news/politics.html",
 * "https://gordonua.com/news/science.html",
 * "https://gordonua.com/news/sport.html",
 * "https://gordonua.com/news/war.html",
 * "https://gordonua.com/news/worldnews.html"
 * })
 */
class Scraper extends \common\components\scrapers\sources\ua\Gordon\uk\Scraper
{
}