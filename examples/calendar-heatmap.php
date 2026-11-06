<?php

use Maantje\Charts\Heatmap\CalendarHeatmap;
use Maantje\Charts\Heatmap\DayOfWeek;

require '../vendor/autoload.php';

mt_srand(42);

$days = [];

for ($date = new DateTimeImmutable('2026-01-01'); $date->format('Y') === '2026'; $date = $date->modify('+1 day')) {
    if (mt_rand(1, 3) === 1) {
        continue;
    }

    $days[$date->format('Y-m-d')] = mt_rand(1, 20);
}

$chart = new CalendarHeatmap(
    days: $days,
    shades: 5,
    start: '2026-01-01',
    end: '2026-12-31',
    weekStart: DayOfWeek::Sunday,
    dayLabels: ['', 'Mon', '', 'Wed', '', 'Fri', ''],
);

echo $chart->render();
