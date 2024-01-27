<?php namespace console\controllers\monitoring;

use Carbon\Carbon;
use common\contracts\Notifier;
use common\models\Country;
use common\models\Language;
use common\services\ArticlesService;
use common\services\notifier\Notification;
use console\controllers\Controller;

class ArticlesController extends Controller
{
    private const START_ALARM_HOUR = 7;
    private const END_ALARM_HOUR = 22;

    private const COUNTRY_MIN_ALARM_PERIOD = 60 * 40;
    private const GLOBAL_MIN_ALARM_PERIOD = 60 * 20;

    /** @var Notifier */
    private $notifier;

    /** @var Carbon */
    private $currentTime;

    /** @var ArticlesService */
    private $articlesService;

    public function __construct($id, $module, Notifier $notifier, ArticlesService $articlesService, $config = [])
    {
        $this->currentTime = Carbon::now();
        $this->articlesService = $articlesService;
        $this->notifier = $notifier;
        parent::__construct($id, $module, $config);
    }

    /**
     * Проверка наличия новых новостей в бд
     */
    public function actionEldestAddedCheck(): void
    {
        $date = $this->articlesService->getLastScrapedArticleDate();

        if (!is_null($date) && $date->diffInSeconds($this->currentTime) >= self::GLOBAL_MIN_ALARM_PERIOD) {
            $notification = new Notification(
                'Не парсятся новости по всем странам',
                'Последняя новость: ' . $date->setTimezone('Europe/Kiev')->toDateTimeString()
            );

            $this->notifier->sendNotification($notification);
        }
        else {
            $this->eldestAddedByCountryCheck();
        }
    }

    /**
     * Проверка наличия новостей по каждой стране отдельно
     */
    private function eldestAddedByCountryCheck(): void
    {
        $notification = new Notification(
            'Парсеры по странам',
            'Не было новостей уже минимум ' . (self::COUNTRY_MIN_ALARM_PERIOD / 60) . ' мин.:'
        );

        $countries = Country::find()->orderBy('code')->all();

        /** @var Country $country */
        foreach ($countries as $country) {
            $timeInCountry = Carbon::now($country->timezone);
            if ($timeInCountry->hour < self::START_ALARM_HOUR || $timeInCountry->hour > self::END_ALARM_HOUR) {
                continue;
            }

            $languages = $country->articlesLanguages ?: [null];

            /** @var Language $language */
            foreach ($languages as $language) {
                $date = $this->articlesService->getLastScrapedArticleDate($country->code, $language->code ?? null);

                if (!is_null($date) && $date->diffInSeconds($this->currentTime) >= self::COUNTRY_MIN_ALARM_PERIOD) {
                    $label = implode('-', array_filter([$country->code, $language->code ?? null]));
                    $notification->addLine('[<b>' . $label . '</b>] последняя новость: ' . $date->setTimezone('Europe/Kiev')->toDateTimeString());
                }
            }
        }

        if ($notification->isChanged()) {
            $this->notifier->sendNotification($notification);
        }
    }
}