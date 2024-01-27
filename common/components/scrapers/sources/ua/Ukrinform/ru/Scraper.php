<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\ua\Ukrinform\ru;

use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\BaseUrls;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\CatchExeptionalParser;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\XPathParserV2;
use common\components\scrapers\common\services\HashImageService;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\NewsCutter;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\SelectorsRemover;
use common\components\scrapers\common\ArticleBodyScraper;
use common\components\scrapers\dto\ArticleItem;
use GuzzleHttp\Promise\Coroutine;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DomCrawler\Crawler;
use common\components\scrapers\common\Config;

/**
 * Class Scraper
 * @package common\components\scrapers\sources\ua\Ukrinform\ru
 *
 * @Config (timezone="Europe/Kiev", urls={
 "https://www.ukrinform.ru/rubric-ato",
"https://www.ukrinform.ru/rubric-crimea",
"https://www.ukrinform.ru/rubric-culture",
"https://www.ukrinform.ru/rubric-economy",
"https://www.ukrinform.ru/rubric-kyiv",
"https://www.ukrinform.ru/rubric-polytics",
"https://www.ukrinform.ru/rubric-regions",
"https://www.ukrinform.ru/rubric-society",
"https://www.ukrinform.ru/rubric-sports",
"https://www.ukrinform.ru/rubric-technology",
"https://www.ukrinform.ru/rubric-tourism",
"https://www.ukrinform.ru/rubric-world"
 * })
 */
class Scraper extends \common\components\scrapers\sources\ua\Ukrinform\uk\Scraper
{
    protected function getHost(): string
    {
        return 'https://www.ukrinform.ru';
    }
}