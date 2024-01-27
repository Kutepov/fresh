<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\ua\Rbc\ru;

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
use GuzzleHttp\Promise\Coroutine;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DomCrawler\Crawler;
use common\components\scrapers\common\Config;

/**
 * Class Scraper
 * @package common\components\scrapers\sources\ua\Rbc\ru
 *
 * @Config (timezone="Europe/Kiev", urls={
 * "https://coronavirus.rbc.ua",
 * "https://www.rbc.ua/rus/politics",
 * "https://www.rbc.ua/rus/accidents",
 * "https://www.rbc.ua/rus/society",
 * "https://www.rbc.ua/rus/economic",
 * "https://www.rbc.ua/rus/finance",
 * "https://www.rbc.ua/rus/hitech",
 * "https://www.rbc.ua/rus/energetics",
 * "https://www.rbc.ua/rus/transport",
 * "https://www.rbc.ua/rus/sport",
 * "https://www.rbc.ua/rus/company"
 * })
 */
class Scraper extends \common\components\scrapers\sources\ua\Rbc\uk\Scraper
{
}