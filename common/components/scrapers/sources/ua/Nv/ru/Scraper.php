<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\ua\Nv\ru;

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
 * @package common\components\scrapers\sources\ua\Nv\ru
 *
 * @Config (timezone="Europe/Kiev", urls={
 * "https://life.nv.ua/znamenitosti.html",
 * "https://nv.ua/art.html",
 * "https://nv.ua/auto.html",
 * "https://nv.ua/biz.html",
 * "https://nv.ua/biz/economics.html",
 * "https://nv.ua/health.html",
 * "https://nv.ua/kyiv.html",
 * "https://nv.ua/sport.html",
 * "https://nv.ua/techno.html",
 * "https://nv.ua/ukraine.html",
 * "https://nv.ua/ukraine/events.html",
 * "https://nv.ua/ukraine/politics.html",
 * "https://nv.ua/world.html"
 * })
 */
class Scraper extends \common\components\scrapers\sources\ua\Nv\uk\Scraper
{
    private const SKIP_NEWS_LABELS = [
        'Новости компаний',
        'НВ Премиум',
    ];

}