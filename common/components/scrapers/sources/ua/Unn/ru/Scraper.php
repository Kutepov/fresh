<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\ua\Unn\ru;

use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\BaseUrls;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\CatchExeptionalParser;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\XPathParserV2;
use common\components\scrapers\common\services\HashImageService;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\NewsCutter;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\SelectorsRemover;
use common\components\scrapers\common\ArticleBodyScraper;
use common\components\scrapers\dto\ArticleBody;
use common\components\scrapers\dto\ArticleBodyNode;
use common\components\scrapers\dto\ArticleItem;
use common\models\Article;
use GuzzleHttp\Promise\Coroutine;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DomCrawler\Crawler;
use common\components\scrapers\common\Config;

/**
 * Class Scraper
 * @package common\components\scrapers\sources\ua\Unn\ru
 *
 * @Config (timezone="Europe/Kiev", urls={
"https://www.unn.com.ua/ru/news/agronews",
"https://www.unn.com.ua/ru/news/criminal",
"https://www.unn.com.ua/ru/news/culture",
"https://www.unn.com.ua/ru/news/economics",
"https://www.unn.com.ua/ru/news/health",
"https://www.unn.com.ua/ru/news/kiev",
"https://www.unn.com.ua/ru/news/lite",
"https://www.unn.com.ua/ru/news/politics",
"https://www.unn.com.ua/ru/news/society",
"https://www.unn.com.ua/ru/news/sport",
"https://www.unn.com.ua/ru/news/tech",
"https://www.unn.com.ua/ru/news/world"
 * })
 */
class Scraper extends \common\components\scrapers\sources\ua\Unn\uk\Scraper
{
}