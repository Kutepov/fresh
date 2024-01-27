<?php namespace common\config\bootstrap;

use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\ExternalServiceHelper\InstagramHelper;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\ExternalServiceHelper\TwitterHelper;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\ExternalServiceHelper\YoutubeHelper;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\NewsCutter;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\TableService;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\BaseUrls;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\XPathParser;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\XPathParserV2;
use Symfony\Component\DomCrawler\Crawler;
use yii\base\BootstrapInterface;

/**
 * Symfony -> Yii migration stuff
 * @package common\config\bootstrap
 */
class Symfony implements BootstrapInterface
{
    public function bootstrap($app)
    {
        $container = \Yii::$container;

        $container->set(\common\components\scrapers\common\symfony\SiteParserModule\Infrastructure\XPathHelper\HashImageService::class, [], ['key' => '1175019452957712']);
        $container->set(\common\components\scrapers\common\symfony\ParsingModule\Infrastructure\HashImageService::class, [], ['key' => '1175019452957712']);
    }
}