<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\ua\Korrespondent\ru;

use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\BaseUrls;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\XPathParserV2;
use common\components\scrapers\common\services\HashImageService;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\NewsCutter;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\SelectorsRemover;
use common\components\scrapers\common\ArticleBodyScraper;
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
 * @package common\components\scrapers\sources\ua\Korrespondent\ru
 *
 * @Config (timezone="Europe/Kiev", urls={
    "https://korrespondent.net/business/auto/",
    "https://korrespondent.net/business/economics/",
    "https://korrespondent.net/city/",
    "https://korrespondent.net/showbiz/",
    "https://korrespondent.net/sport/",
    "https://korrespondent.net/tech/medicine/",
    "https://korrespondent.net/tech/science/",
    "https://korrespondent.net/ukraine/politics/",
    "https://korrespondent.net/world/",
 * })
 */
class Scraper extends \common\components\scrapers\sources\ua\Korrespondent\uk\Scraper
{
}