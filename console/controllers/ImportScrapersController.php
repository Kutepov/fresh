<?php namespace console\controllers;

use common\components\scrapers\common\ArticleBodyScraper;
use common\components\scrapers\common\exceptions\ScraperValidationException;
use common\components\scrapers\services\ScrapersService;
use common\models\Source;
use common\models\SourceUrl;
use Doctrine\Common\Annotations\AnnotationReader;
use yii\helpers\ArrayHelper;
use yii\helpers\Console;

class ImportScrapersController extends Controller
{
    private $annotationReader;
    private $scrapersService;
    public $skipExists = false;
    public $country;

    public function options($actionID)
    {
        return ArrayHelper::merge(parent::options($actionID), [
            'skipExists',
            'country',
        ]);
    }

    public function __construct($id, $module, AnnotationReader $annotationReader, ScrapersService $scrapersService, $config = [])
    {
        $this->annotationReader = $annotationReader;
        $this->scrapersService = $scrapersService;

        parent::__construct($id, $module, $config);
    }

    public function actionIndex()
    {
        $classes = $this->scrapersService->findAllScrapersClasses();

        foreach ($classes as $k => $className) {
            $classInstance = \Yii::createObject($className);

            $this->stdout('Обработка класса ' . $className, Console::FG_GREEN);

            try {
                $scraperConfig = $this->scrapersService->getConfigByClassName($className);
            } catch (ScraperValidationException $e) {
                $this->stderr($e->getMessage());
                exit;
            }
            $classNameParts = explode('\\', $className);
            array_pop($classNameParts);

            $classCountry = strtoupper($classNameParts[4]);

            if ($this->country && $this->country !== $classCountry) {
                continue;
            }

            if ($m = SourceUrl::findOne(['url' => $scraperConfig->urls])) {
                if ($this->skipExists) {
                    continue;
                }
                $source = $m->source;
            }
            else {
                $this->stdout('Необходимо создать новый источник для класса' . $className);

                $existsId = $this->prompt('ID существующего источника (необязательно)', [
                    'default' => null,
                    'required' => false
                ]);

                if ($existsId === '0') {
                    continue;
                }

                if (is_null($existsId) || !($source = Source::findOne(['id' => $existsId]))) {
                    $source = new Source(['enabled' => 1]);

                    $source->type = $classInstance instanceof ArticleBodyScraper ? 'full-news-item' : 'webview';

                    $urlInfo = parse_url(reset($scraperConfig->urls));

                    $source->name = $this->prompt('Название источника', [
                        'default' => preg_replace('#^www\.#i', '', $urlInfo['host']),
                        'required' => true
                    ]);

                    $source->url = $this->prompt('URL главной страницы источника (например https://domain.com)', [
                        'default' => $urlInfo['scheme'] . '://' . $urlInfo['host'],
                        'required' => true,
                        'validator' => function ($input, &$error) {
                            if (!filter_var($input, FILTER_VALIDATE_URL)) {
                                $error = 'Невалидный URL';
                                return false;
                            }

                            return true;
                        }
                    ]);

                    $source->country = $this->prompt('ISO 3166-1 двубуквенный код страны источника (например: ua)', [
                        'required' => true,
                        'pattern' => '#^[a-z]{2}$#',
                        'default' => $classCountry
                    ]);

                    $source->timezone = $this->prompt('Часовой пояс новостей в источнике (например: Europe/Kiev)', [
                        'required' => true,
                        'default' => $scraperConfig->timezone,
                        'validator' => function ($input, &$error) {
                            if (!$this->validateTimezone($input)) {
                                $error = 'Неверный часовой пояс: ' . $input;
                                return false;
                            }

                            return true;
                        }
                    ]);

                    if (!$source->save()) {
                        $this->stderr('Ошибка при сохранении источника: :'.print_r($source->getErrors(), true));
                    }

                    $this->stdout($source->name.' new id: '.$source->id, Console::FG_YELLOW);
                }
            }

            if (!$source->timezone) {
                $source->updateAttributes([
                    'timezone' => $scraperConfig->timezone
                ]);
                $this->stdout('Часовой пояс источника установлен: ' . $scraperConfig->timezone);
            }

            if ($source->enabled === null) {
                $source->updateAttributes(['enabled' => 1]);
                $this->stdout('Источник активирован');
            }

            if (!$source->url) {
                $url = parse_url($scraperConfig->urls[0]);
                $url = $url['scheme'] . '://' . $url['host'];

                $source->updateAttributes([
                    'url' => $url
                ]);

                $this->stdout('URL источника установлен: ' . $url);
            }

            if (!$source->type) {
                $source->updateAttributes([
                    'type' => $type = $classInstance instanceof ArticleBodyScraper ? 'preview' : 'webview'
                ]);

                $this->stdout('Тип новостей источника установлен: ' . $type);
            }

            $urls = array_unique($scraperConfig->urls);
            foreach ($urls as $sourceUrl) {
                if ($sourceUrlModel = SourceUrl::findOne(['url' => $sourceUrl])) {
                    if ($sourceUrlModel->class != $className) {
                        $sourceUrlModel->updateAttributes([
                            'class' => $className,
                            'enabled' => $sourceUrlModel->enabled === null ? 1 : 0
                        ]);
                        $this->stdout('Класс для URL ' . $sourceUrl . ' обновлен');
                    }

                    if (!$sourceUrlModel->timezone) {
                        $this->stdout('Часовой пояс для URL ' . $sourceUrl . ' установлен: ' . $scraperConfig->timezone);
                        $sourceUrlModel->updateAttributes([
                            'timezone' => $scraperConfig->timezone
                        ]);
                    }
                }
                else {
                    $sourceUrlModel = new SourceUrl([
                        'class' => $className,
                        'enabled' => 1,
                        'category_id' => null,
                        'source_id' => $source->id,
                        'timezone' => $scraperConfig->timezone,
                        'url' => $sourceUrl
                    ]);
                    if ($sourceUrlModel->save(false)) {
                        $this->stdout('Новый URL для источника создан: ' . $sourceUrl);
                    }
                    else {
                        $this->stderr('Ошибка при создании URL для источника: ' . implode(', ', $sourceUrlModel->firstErrors));
                        \Yii::$app->end();
                    }
                }
            }
        }
    }

    private function validateTimezone($timezone)
    {
        $availableTimeZones = \DateTimeZone::listIdentifiers(\DateTimeZone::ALL);
        return in_array($timezone, $availableTimeZones);
    }
}