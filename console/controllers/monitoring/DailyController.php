<?php namespace console\controllers\monitoring;

use Carbon\Carbon;
use common\contracts\Notifier;
use common\models\Country;
use common\services\ArticlesService;
use common\services\notifier\Notification;
use common\services\SourcesService;
use console\controllers\Controller;
use yii\base\Exception;

class DailyController extends Controller
{
    /** @var Notifier */
    private $notifier;

    /** @var ArticlesService */
    private $articlesService;

    /** @var SourcesService */
    private $sourcesService;

    private const SOURCE_ALERT_AMOUNT_WEEKDAY_RATE = -30;
    private const SOURCE_ALERT_AMOUNT_WEEKEND_RATE = -40;

    public function __construct($id, $module, Notifier $notifier, ArticlesService $articlesService, SourcesService $sourcesService, $config = [])
    {
        $this->notifier = $notifier;
        $this->articlesService = $articlesService;
        $this->sourcesService = $sourcesService;
        parent::__construct($id, $module, $config);
    }

    public function actionArticlesAmountByCountry($timeInterval = null): void
    {
        if (is_null($timeInterval)) {
            $notification = new Notification('Отчет по странам за прошедшие сутки по сравнению с прошлой неделей:');
        }
        else {
            [$startTime, $endTime] = $this->getTimeInterval($timeInterval);
            $notification = new Notification('Отчет по странам за ' . $startTime->toTimeString('minute') . ' - ' . $endTime->toTimeString('minute') . ' (МСК) по сравнению с прошлой неделей:');
            $startTime->setTimezone('UTC');
            $endTime->setTimezone('UTC');
        }

        $countries = Country::all();

        /** @var Country $country */
        foreach ($countries as $country) {
            $languages = $country->articlesLanguages ?: [null];
            foreach ($languages as $language) {
                [$firstPeriodStart, $firstPeriodEnd, $secondPeriodStart, $secondPeriodEnd] = $this->createPeriods($timeInterval);

                $yesterdayAmount = $this->articlesService->getAmountByCountry(
                    $country->code,
                    $firstPeriodStart,
                    $firstPeriodEnd,
                    $language->code ?? null
                );

                $lastWeekAmount = $this->articlesService->getAmountByCountry(
                    $country->code,
                    $secondPeriodStart,
                    $secondPeriodEnd,
                    $language->code ?? null
                );

                $label = implode('-', array_filter([$country->code, $language->code ?? null]));

                $diff = $yesterdayAmount - $lastWeekAmount;
                if ($diff > 0) {
                    $diff = '+' . $diff;
                }

                if ($lastWeekAmount > 0) {
                    $diffInPercents = round(($yesterdayAmount / $lastWeekAmount - 1) * 100);
                    if ($diffInPercents > 0) {
                        $diffInPercents = '+' . $diffInPercents;
                    }
                }
                else {
                    $diffInPercents = '+100';
                }

                $notification->addLine(
                    '<b>[' . $label . ']</b> новостей: ' . $yesterdayAmount . ($diff !== 0 ? ' (' . $diff . ' | ' . $diffInPercents . '%)' : '')
                );
            }
        }

        if ($this->debug) {
            die($notification->getNotificationBody());
        }
        $this->notifier->sendNotification($notification);
    }

    public function actionArticlesAmountBySources($timeInterval = null): void
    {
        if (is_null($timeInterval)) {
            $notification = new Notification('Отчет по источникам за прошедшие сутки по сравнению с прошлой неделей:');
        }
        else {
            [$startTime, $endTime] = $this->getTimeInterval($timeInterval);
            $notification = new Notification('Отчет по источникам за ' . $startTime->toTimeString('minute') . ' - ' . $endTime->toTimeString('minute') . ' (МСК) по сравнению с прошлой неделей:');
        }

        $sources = $this->sourcesService->getEnabledSources();

        $report = [];

        foreach ($sources as $source) {
            [$firstPeriodStart, $firstPeriodEnd, $secondPeriodStart, $secondPeriodEnd] = $this->createPeriods($timeInterval);

            $yesterdayAmount = $this->articlesService->getAmountBySource(
                $source->id,
                $firstPeriodStart,
                $firstPeriodEnd
            );

            $lastWeekAmount = $this->articlesService->getAmountBySource(
                $source->id,
                $secondPeriodStart,
                $secondPeriodEnd
            );

            if ($yesterdayAmount < $lastWeekAmount || $yesterdayAmount === 0) {
                if ($lastWeekAmount === 0) {
                    $diffInPercents = 0;
                }
                else {
                    $diffInPercents = round(($yesterdayAmount / $lastWeekAmount - 1) * 100);
                }
                $diff = $yesterdayAmount - $lastWeekAmount;

                if (
                    ($diffInPercents === 0 && $yesterdayAmount === 0) ||
                    ($firstPeriodStart->isWeekday() && $diffInPercents <= self::SOURCE_ALERT_AMOUNT_WEEKDAY_RATE) ||
                    ($firstPeriodEnd->isWeekend() && $diffInPercents <= self::SOURCE_ALERT_AMOUNT_WEEKEND_RATE)
                ) {
                    $report[$source->country][] = ($source->language ? '[' . $source->language . '] ' : '') .
                        $source->name . ': ' . $yesterdayAmount . ($diffInPercents ? ' (' . $diff . ' | ' . $diffInPercents . '%)' : '');
                }
            }
        }

        if (count($report)) {
            foreach ($report as $country => $issues) {
                $notification->addLine('<b>' . $country . ':</b>');
                foreach ($issues as $issue) {
                    $notification->addLine($issue);
                }
            }

            if ($this->debug) {
                die($notification->getNotificationBody());
            }
            $this->notifier->sendNotification($notification);
        }
    }

    /**
     * @param string|null $timeInterval
     * @return Carbon[]
     */
    private function createPeriods(?string $timeInterval = null): array
    {
        if (!is_null($timeInterval)) {
            [$startTime, $endTime] = $this->getTimeInterval($timeInterval);

            $firstPeriodStart = $startTime;
            $firstPeriodEnd = $endTime;

            $secondPeriodStart = Carbon::today('Europe/Moscow')->subWeek()->setTime($startTime->hour, $startTime->minute);
            $secondPeriodEnd = Carbon::today('Europe/Moscow')->subWeek()->setTime($endTime->hour, $endTime->minute);
        }
        else {
            $firstPeriodStart = Carbon::yesterday();
            $firstPeriodEnd = Carbon::today();

            $secondPeriodStart = Carbon::yesterday()->subWeek();
            $secondPeriodEnd = Carbon::yesterday()->subWeek()->endOfDay();
        }

        return [$firstPeriodStart, $firstPeriodEnd, $secondPeriodStart, $secondPeriodEnd];
    }

    /**
     * @param string $time
     * @return Carbon[]
     * @throws Exception
     */
    private function getTimeInterval(string $time): array
    {
        if (preg_match('#^\d{2}:\d{2}-\d{2}:\d{2}$#', $time)) {
            [$startTime, $endTime] = explode('-', $time);
            $startTime = Carbon::parse($startTime, 'Europe/Moscow');
            $endTime = Carbon::parse($endTime, 'Europe/Moscow');

            return [$startTime, $endTime];
        }

        throw new Exception('Wrong time interval format:' . $time);
    }
}