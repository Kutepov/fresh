<?php
/**
 * @var \omnilight\scheduling\Schedule $schedule
 */
/** Перенос кликов по новостям из Redis в MySQL */
//$schedule->command('statistics/articles/store-clicks')->withoutOverlapping()->everyNMinutes(2);

/** Перенос просмотров новостей из Redis в MySQL */
//$schedule->command('statistics/articles/store-views')->withoutOverlapping()->everyNMinutes(3);

/** Сбор агрегированной статистики по кликам и показам новостей */
$schedule->command('statistics/search/top-queries')->withoutOverlapping()->everyNMinutes(30);
$schedule->command('statistics/articles/aggregate')->withoutOverlapping()->everyNMinutes(1);
//$schedule->command('statistics/articles/aggregate-clicks UA')->withoutOverlapping()->everyNMinutes(2);
$schedule->command('telegram/approve-requests')->withoutOverlapping()->everyNMinutes(5);
//$schedule->command('statistics/articles/daily-cache')->withoutOverlapping()->everyNMinutes(15);

/** Проверка наличия новостей в категориях (и папках) для всех стран */
$schedule->command('categories/check-articles-exists')->withoutOverlapping()->everyNMinutes(10);

$schedule->command('scrapers/unlock-sources-urls')->withoutOverlapping()->everyNMinutes(5);

$schedule->command('scrapers/clear-locks-logs')->withoutOverlapping()->dailyAt('00:00');

/** Проверка на наличие всех новостей и по каждой стране/языку отдельно */
$schedule->command('monitoring/articles/eldest-added-check')->withoutOverlapping()->everyNMinutes(20);

/** Ежедневный отчет по количеству новостей в разных странах */
$schedule->command('monitoring/daily/articles-amount-by-country')->dailyAt('06:00'); //UTC

/** Ежедневный отчет по источникам, которые, возможно, отвалились */
$schedule->command('monitoring/daily/articles-amount-by-sources')->dailyAt('06:00'); //UTC

/** Обновление агрегированой статистики */
$schedule->command('statistics/historical')->everyNMinutes(6);
$schedule->command('statistics/historical/push-notifications')->everyNMinutes(6);

$schedule->command('sitemap/generate')->dailyAt('02:00');