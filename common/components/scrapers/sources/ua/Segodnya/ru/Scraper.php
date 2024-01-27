<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\ua\Segodnya\ru;

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
 * @package common\components\scrapers\sources\ua\Segodnya\ru
 *
 * @Config (timezone="Europe/Kiev", urls={
"https://economics.segodnya.ua",
"https://lifestyle.segodnya.ua",
"https://politics.segodnya.ua",
"https://sport.segodnya.ua",
"https://ukraine.segodnya.ua",
"https://world.segodnya.ua"
 * })
 */
class Scraper extends \common\components\scrapers\sources\ua\Segodnya\uk\Scraper
{
}