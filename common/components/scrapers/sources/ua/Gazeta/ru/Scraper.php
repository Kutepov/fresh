<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\ua\Gazeta\ru;

use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\XPathParserV2;
use common\components\scrapers\common\services\HashImageService;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\NewsCutter;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\SelectorsRemover;
use common\components\scrapers\common\ArticleBodyScraper;
use common\components\scrapers\dto\ArticleBody;
use common\components\scrapers\dto\ArticleBodyNode;
use common\components\scrapers\dto\ArticleItem;
use GuzzleHttp\Promise\Coroutine;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DomCrawler\Crawler;
use common\components\scrapers\common\Config;

/**
 * Class Scraper
 * @package common\components\scrapers\sources\ua\Gazeta\ru
 *
 * @Config (timezone="Europe/Kiev", urls={
"https://api.gazeta.ua/ru/api/section/stream&?category=avto",
"https://api.gazeta.ua/ru/api/section/stream&?category=house",
"https://api.gazeta.ua/ru/api/section/stream&?category=donbas",
"https://api.gazeta.ua/ru/api/section/stream&?category=health",
"https://api.gazeta.ua/ru/api/section/stream&?category=history",
"https://api.gazeta.ua/ru/api/section/stream?category=krym",
"https://api.gazeta.ua/ru/api/section/stream?category=culture",
"https://api.gazeta.ua/ru/api/section/stream?category=economics",
"https://api.gazeta.ua/ru/api/section/stream?category=politics",
"https://api.gazeta.ua/ru/api/section/stream?category=celebrities",
"https://api.gazeta.ua/ru/api/section/stream?category=edu-and-science",
"https://api.gazeta.ua/ru/api/section/stream?category=life",
"https://api.gazeta.ua/ru/api/section/stream?category=sport"
 * })
 */
class Scraper extends \common\components\scrapers\sources\ua\Gazeta\uk\Scraper
{
}