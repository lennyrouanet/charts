<?php

use Maantje\Charts\Bar\Bar;
use Maantje\Charts\Bar\Bars;
use Maantje\Charts\Boxplot\Boxplot;
use Maantje\Charts\Boxplot\Boxplots;
use Maantje\Charts\Chart;
use Maantje\Charts\Line\Lines;
use Maantje\Charts\Line\SlotLine;

// max 100: left margin 60, width 710, two slots centred on 237.5 and 592.5; y(v) = 550 - 5.25 * v
function slotLineChart(SlotLine $line): string
{
    return (new Chart(
        series: [
            new Boxplots(boxplots: [
                new Boxplot(name: 'Jan', values: [20, 40, 50, 60, 100]),
                new Boxplot(name: 'Feb', values: [10, 20, 30, 40, 50]),
            ]),
            new Lines(lines: [$line]),
        ],
    ))->render();
}

it('places each value at the centre of its slot', function () {
    expect(slotLineChart(new SlotLine(values: [50, 30])))
        ->toContain('<text x="237.5" y="580"')
        ->toContain('<text x="592.5" y="580"')
        ->toContain('d="M 237.5,287.5 L 592.5,392.5"');
});

it('does not add a scale to the x axis', function () {
    $svg = slotLineChart(new SlotLine(values: [50, 30]));

    // x axis ticks go from y=550 to y=545, x axis labels sit at y=575
    expect($svg)->not->toContain('y2="545"');
    expect($svg)->not->toContain('y="575"');
});

it('aligns with bars', function () {
    $chart = new Chart(
        series: [
            new Bars(bars: [new Bar(name: 'A', value: 100), new Bar(name: 'B', value: 50)]),
            new Lines(lines: [new SlotLine(values: [100, 50])]),
        ],
    );

    expect($chart->render())->toContain('d="M 237.5,25 L 592.5,287.5"');
});

it('draws no points by default', function () {
    expect(slotLineChart(new SlotLine(values: [50, 30])))->not->toContain('<circle');
});

it('draws points when a point color is given', function () {
    expect(slotLineChart(new SlotLine(values: [50, 30], pointColor: 'red')))
        ->toContain('<circle cx="237.5" cy="287.5" r="5" fill="red" stroke="none" stroke-width="0"><title>50</title></circle>')
        ->toContain('<circle cx="592.5" cy="392.5" r="5" fill="red" stroke="none" stroke-width="0"><title>30</title></circle>');
});

it('applies line options', function () {
    expect(slotLineChart(new SlotLine(values: [50, 30], size: 2, color: 'red', stepLine: true)))
        ->toContain('d="M 237.5,287.5 H 592.5 V 392.5" fill="none" stroke="red" stroke-width="2"');
});

it('takes part in the y axis range', function () {
    $lines = new Lines(lines: [new SlotLine(values: [10, 150])]);

    expect($lines->minValue())->toBe(10.0)
        ->and($lines->maxValue())->toBe(150.0);
});

it('renders nothing when empty', function () {
    $svg = slotLineChart(new SlotLine(values: []));

    expect($svg)->toContain('</svg>');
    expect($svg)->not->toContain('<path');
});
