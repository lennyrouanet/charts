<?php

require '../vendor/autoload.php';

use Maantje\Charts\Boxplot\Boxplot;
use Maantje\Charts\Boxplot\Boxplots;
use Maantje\Charts\Chart;
use Maantje\Charts\Line\Lines;
use Maantje\Charts\Line\SlotLine;

$chart = new Chart(
    series: [
        new Boxplots(
            boxplots: [
                new Boxplot(
                    name: 'January',
                    values: [12, 30, 45, 60, 88],
                ),
                new Boxplot(
                    name: 'February',
                    values: [20, 35, 50, 70, 95, 130, 4],
                ),
                new Boxplot(
                    name: 'March',
                    values: [8, 22, 38, 55, 76, 110],
                ),
            ],
        ),
        new Lines(
            lines: [
                new SlotLine(
                    values: [48, 56, 41],
                    size: 2,
                    color: '#e74c3c',
                    pointColor: '#e74c3c',
                ),
            ],
        ),
    ],
);

echo $chart->render();
