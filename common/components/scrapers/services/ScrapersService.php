<?php namespace common\components\scrapers\services;

use common\components\scrapers\common\ArticleBodyScraper;
use common\components\scrapers\common\ArticlesListScraper;
use common\components\scrapers\common\Config;
use common\components\scrapers\common\exceptions\ScraperValidationException;
use common\components\scrapers\common\Scraper;
use common\services\ClassFinder;
use Doctrine\Common\Annotations\AnnotationReader;
use yii\base\BaseObject;

class ScrapersService extends BaseObject
{
    public const PATH_TO_SOURCES = 'common\components\scrapers\sources';

    private $classFinder;
    private $annotationReader;

    public function __construct(AnnotationReader $annotationReader, ClassFinder $classFinder, $config = [])
    {
        $this->annotationReader = $annotationReader;
        $this->classFinder = $classFinder;
        parent::__construct($config);
    }

    /**
     * Чтение конфига из аннотации и валидация
     * @param $className
     * @return Config
     * @throws ScraperValidationException
     * @throws \ReflectionException
     * @throws \yii\base\InvalidConfigException
     */
    public function getConfigByClassName($className): Config
    {
        $reflectionClass = new \ReflectionClass($className);

        if (!$reflectionClass->getParentClass()) {
            throw new ScraperValidationException('Класс не наследует базовый абстрактный класс.');
        }

        /** @var Config $scraperConfig */
        $scraperConfig = $this->annotationReader->getClassAnnotation($reflectionClass, Config::class);
        if (is_null($scraperConfig)) {
            throw new ScraperValidationException('Не найдена аннотация для класса.');
        }

        /** @var Scraper $class */
        $class = \Yii::createObject($className);

        /** Валидация класса парсера */
        if (!($class instanceof ArticleBodyScraper) && !($class instanceof ArticlesListScraper)) {
            throw new ScraperValidationException('Класс не реализует необходимые интерфейсы.');
        }

        try {
            new \DateTimeZone($scraperConfig->timezone);
        } catch (\Exception $e) {
            throw new ScraperValidationException('Неверный часовой пояс: ' . $scraperConfig->timezone);
        }

        if (!is_array($scraperConfig->urls) || !count($scraperConfig->urls)) {
            throw new ScraperValidationException('В аннотации класса не заданы URL источников.');
        }

        array_map(function ($url) {
            $urlComponents = parse_url($url);

            if (!filter_var($url, FILTER_VALIDATE_URL) ||
                !in_array($urlComponents['scheme'], ['http', 'https'])
            ) {
                throw new ScraperValidationException('В списке присутствует невалидный URL: ' . $url);
            }
        }, $scraperConfig->urls);

        return $scraperConfig;
    }

    /**
     * @param null|string $country
     * @return string[]
     */
    public function findAllScrapersClasses($country = null)
    {
        return $this->classFinder->findClassesInNamespace(self::PATH_TO_SOURCES . (!is_null($country) ? '\\' . $country : ''));
    }
}