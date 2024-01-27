<?php namespace backend\models\search\statistics;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use yii\db\Expression;

trait Calendar
{
    public $dateInterval;

    protected function createDefaultDatesInterval($daysAmount = 6): void
    {
        $today = (new \DateTime('today'));
        $todayString = $today->format('Y-m-d');
        $this->dateInterval = $today->modify(sprintf('-%d days', $daysAmount))->format('Y-m-d') . ' - ' . $todayString;
    }

    protected function dateCondition($column = 'date', $isMainQuery = false): array
    {
        if (strpos($this->dateInterval, ' - ') === false) {
            if ($isMainQuery) {
                return [
                    $column => new Expression("CAST('" . $this->dateInterval . "' as DATE)")
                ];
            }
            else {
                return [
                    'BETWEEN',
                    $column,
                    Carbon::parse($this->dateInterval . ' 00:00:00', 'Europe/Kiev')->setTimezone('UTC')->toDateTimeString(),
                    Carbon::parse($this->dateInterval . ' 23:59:59', 'Europe/Kiev')->setTimezone('UTC')->toDateTimeString(),
                ];
            }
        }

        [$start, $end] = explode(' - ', $this->dateInterval);
        if ($isMainQuery) {
            return [
                'BETWEEN',
                $column,
                new Expression("CAST('" . $start . "' as DATE)"),
                new Expression("CAST('" . $end . "' as DATE)")
            ];
        }
        else {
            return [
                'BETWEEN',
                $column,
                Carbon::parse($start . ' 00:00:00', 'Europe/Kiev')->setTimezone('UTC')->toDateTimeString(),
                Carbon::parse($end . ' 23:59:59', 'Europe/Kiev')->setTimezone('UTC')->toDateTimeString(),
            ];
        }
    }

    protected function getDatesIntervalDaysAmount(): int
    {
        if (strpos($this->dateInterval, ' - ') === false) {
            return 1;
        }

        [$start, $end] = explode(' - ', $this->dateInterval);
        $period = CarbonPeriod::create($start, $end);
        return $period->count();
    }
}