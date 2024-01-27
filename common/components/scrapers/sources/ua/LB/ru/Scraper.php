<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\ua\LB\ru;

use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\ExternalServiceHelper\InstagramHelper;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\ExternalServiceHelper\YoutubeHelper;
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
 * @package common\components\scrapers\sources\ua\LB\ru
 *
 * @Config (timezone="Europe/Kiev", urls={
 * "https://rus.lb.ua/politics",
 * "https://rus.lb.ua/economics",
 * "https://rus.lb.ua/society",
 * "https://rus.lb.ua/culture",
 * "https://rus.lb.ua/world",
 * "https://rus.lb.ua/sport"
 * })
 */
class Scraper extends \common\components\scrapers\sources\ua\LB\uk\Scraper
{
    protected function getHost(): string
    {
        return 'https://rus.lb.ua';
    }

}