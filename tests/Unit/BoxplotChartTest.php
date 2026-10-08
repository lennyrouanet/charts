<?php

use Maantje\Charts\Bar\Bar;
use Maantje\Charts\Bar\Bars;
use Maantje\Charts\Boxplot\Boxplot;
use Maantje\Charts\Boxplot\Boxplots;
use Maantje\Charts\Chart;
use Maantje\Charts\YAxis;

function boxplotChart(Boxplot ...$boxplots): string
{
    return (new Chart(series: [new Boxplots(boxplots: $boxplots)]))->render();
}

it('exposes min and max including outliers', function () {
    $boxplot = new Boxplot(name: 'Jan', values: [20, 40, 50, 60, 80, 100, 10]);

    expect($boxplot->minValue())->toBe(10.0)
        ->and($boxplot->maxValue())->toBe(100.0);
});

it('reads values by position whatever the array keys', function () {
    $boxplot = new Boxplot(name: 'Jan', values: [3 => 20, 7 => 40, 'm' => 50, 9 => 60, 10 => 100]);

    expect($boxplot->values)->toBe([20, 40, 50, 60, 100]);
});

it('accepts equal values', function () {
    $boxplot = new Boxplot(name: 'Jan', values: [5, 5, 5, 5, 5]);

    expect($boxplot->minValue())->toBe(5.0)
        ->and($boxplot->maxValue())->toBe(5.0);
});

it('rejects fewer than five values', function () {
    new Boxplot(name: 'Jan', values: [1, 2, 3, 4]);
})->throws(
    InvalidArgumentException::class,
    'Boxplot "Jan" needs at least 5 values (min, Q1, median, Q3, max), 4 given.'
);

it('rejects unordered values', function () {
    new Boxplot(name: 'Jan', values: [20, 60, 50, 40, 100]);
})->throws(
    InvalidArgumentException::class,
    'Boxplot "Jan" values must be ordered: min <= Q1 <= median <= Q3 <= max.'
);

it('renders a boxplot', function () {
    $svg = boxplotChart(new Boxplot(name: 'Jan', values: [20, 40, 50, 60, 100]));

    $whisker = '<line x1="415" y1="445" x2="415" y2="25" stroke="#333" stroke-dasharray="" stroke-width="2" />';
    $box = '<rect x="385" y="235" width="60" height="105" fill="#3498db" fill-opacity="1" stroke="#333" stroke-width="2" rx="0" ry="0"><title>20 / 40 / 50 / 60 / 100</title></rect>';

    expect($svg)
        ->toContain($whisker)
        ->toContain('<line x1="400" y1="445" x2="430" y2="445" stroke="#333" stroke-dasharray="" stroke-width="2" />')
        ->toContain('<line x1="400" y1="25" x2="430" y2="25" stroke="#333" stroke-dasharray="" stroke-width="2" />')
        ->toContain($box)
        ->toContain('<line x1="385" y1="287.5" x2="445" y2="287.5" stroke="#333" stroke-dasharray="" stroke-width="2" />')
        ->toContain('<text x="415" y="580" font-family="arial" font-size="14" fill="#333" stroke="none" stroke-width="0" text-anchor="middle" dominant-baseline="alphabetic" alignment-baseline="">Jan</text>')
        ->and((int) strpos($svg, $whisker))->toBeLessThan((int) strpos($svg, $box));

    expect($svg)->not->toContain('<circle');
});

it('renders outliers as points and keeps them inside the scale', function () {
    $svg = boxplotChart(new Boxplot(name: 'Jan', values: [20, 40, 50, 60, 80, 100, 10]));

    expect($svg)
        ->toContain('<line x1="415" y1="445" x2="415" y2="130"')
        ->toContain('<circle cx="415" cy="25" r="4" fill="#333" stroke="none" stroke-width="0"><title>100</title></circle>')
        ->toContain('<circle cx="415" cy="497.5" r="4" fill="#333" stroke="none" stroke-width="0"><title>10</title></circle>')
        ->and(substr_count($svg, '<circle'))->toBe(2);
});

it('applies styling parameters', function () {
    $svg = boxplotChart(new Boxplot(
        name: 'Jan',
        values: [20, 40, 50, 60, 100, 10],
        color: 'black',
        strokeWidth: 3,
        fillColor: 'yellow',
        medianColor: 'red',
        outlierColor: 'green',
        outlierSize: 6,
        labelColor: 'blue',
        labelMarginY: 20,
    ));

    expect($svg)
        ->toContain('fill="yellow" fill-opacity="1" stroke="black" stroke-width="3"')
        ->toContain('<line x1="385" y1="287.5" x2="445" y2="287.5" stroke="red" stroke-dasharray="" stroke-width="3" />')
        ->toContain('<circle cx="415" cy="497.5" r="6" fill="green"')
        ->toContain('<text x="415" y="570" font-family="arial" font-size="14" fill="blue"');
});

it('fills the slot when width is null and never exceeds it', function (?float $width) {
    $svg = boxplotChart(new Boxplot(name: 'Jan', values: [20, 40, 50, 60, 100], width: $width));

    expect($svg)->toContain('<rect x="60" y="235" width="710" height="105"');
})->with([[null], [5000.0]]);

it('omits the label when name is empty', function () {
    expect(boxplotChart(new Boxplot(name: '', values: [20, 40, 50, 60, 100])))
        ->not->toContain('y="580"');
});

it('escapes the label', function () {
    expect(boxplotChart(new Boxplot(name: '<b>"A&B"</b>', values: [20, 40, 50, 60, 100])))
        ->toContain('>&lt;b&gt;&quot;A&amp;B&quot;&lt;/b&gt;</text>');
});

it('renders on a named y axis', function () {
    $chart = new Chart(
        yAxis: [new YAxis(minValue: 0), new YAxis(name: 'right', minValue: 0, maxValue: 200)],
        series: [
            new Bars(bars: [new Bar(name: 'A', value: 100)]),
            new Boxplots(
                boxplots: [new Boxplot(name: '', values: [20, 40, 50, 60, 100], yAxis: 'right')],
                yAxis: 'right',
            ),
        ],
    );

    // two Y axes: left margin 110, width 660; y(v) = 550 - 2.625 * v
    expect($chart->render())->toContain('<rect x="410" y="392.5" width="60" height="52.5" fill="#3498db"');
});

it('aligns with bars slot by slot', function () {
    $chart = new Chart(
        series: [
            new Bars(bars: [new Bar(name: 'A', value: 100), new Bar(name: 'B', value: 50)]),
            new Boxplots(boxplots: [
                new Boxplot(name: '', values: [20, 40, 50, 60, 100]),
                new Boxplot(name: '', values: [10, 20, 30, 40, 50]),
            ]),
        ],
    );

    expect($chart->render())
        ->toContain('<text x="237.5" y="580"')
        ->toContain('<line x1="237.5" y1="445" x2="237.5" y2="25"')
        ->toContain('<text x="592.5" y="580"')
        ->toContain('<line x1="592.5" y1="497.5" x2="592.5" y2="287.5"');
});

it('aggregates min and max across boxplots', function () {
    $boxplots = new Boxplots(boxplots: [
        new Boxplot(name: 'A', values: [20, 40, 50, 60, 100]),
        new Boxplot(name: 'B', values: [10, 20, 30, 40, 50, 130]),
    ]);

    expect($boxplots->minValue())->toBe(10.0)
        ->and($boxplots->maxValue())->toBe(130.0);
});

it('renders nothing when empty', function () {
    expect((new Boxplots)->render(new Chart))->toBe('');
});

it('renders the full boxplot chart svg', function () {
    expect(boxplotChart(new Boxplot(name: 'Jan', values: [20, 40, 50, 60, 100])))->toBe(<<<'SVG'
<svg width="800" height="600"  xmlns="http://www.w3.org/2000/svg">
    <rect x="0" y="0" width="800" height="600" fill="white" fill-opacity="1" stroke="none" stroke-width="0" rx="0" ry="0"><title></title></rect>
    <text x="50" y="555" font-family="arial" font-size="14" fill="black" stroke="none" stroke-width="0" text-anchor="end" dominant-baseline="alphabetic" alignment-baseline="">0</text><text x="50" y="450" font-family="arial" font-size="14" fill="black" stroke="none" stroke-width="0" text-anchor="end" dominant-baseline="alphabetic" alignment-baseline="">20</text><text x="50" y="345" font-family="arial" font-size="14" fill="black" stroke="none" stroke-width="0" text-anchor="end" dominant-baseline="alphabetic" alignment-baseline="">40</text><text x="50" y="240" font-family="arial" font-size="14" fill="black" stroke="none" stroke-width="0" text-anchor="end" dominant-baseline="alphabetic" alignment-baseline="">60</text><text x="50" y="135" font-family="arial" font-size="14" fill="black" stroke="none" stroke-width="0" text-anchor="end" dominant-baseline="alphabetic" alignment-baseline="">80</text><text x="50" y="30" font-family="arial" font-size="14" fill="black" stroke="none" stroke-width="0" text-anchor="end" dominant-baseline="alphabetic" alignment-baseline="">100</text><text x="20" y="262.5" font-family="arial" font-size="14" fill="black" stroke="none" stroke-width="0" text-anchor="middle" dominant-baseline="alphabetic" alignment-baseline="middle" transform="rotate(270, 20, 262.5)"></text>
    <line x1="60" y1="550" x2="770" y2="550" stroke="black" stroke-dasharray="" stroke-width="1" /><text x="415" y="590" font-family="arial" font-size="14" fill="black" stroke="none" stroke-width="0" text-anchor="middle" dominant-baseline="alphabetic" alignment-baseline=""></text>
    <line x1="60" y1="550" x2="770" y2="550" stroke="#ccc" stroke-dasharray="" stroke-width="1" /><line x1="60" y1="445" x2="770" y2="445" stroke="#ccc" stroke-dasharray="" stroke-width="1" /><line x1="60" y1="340" x2="770" y2="340" stroke="#ccc" stroke-dasharray="" stroke-width="1" /><line x1="60" y1="235" x2="770" y2="235" stroke="#ccc" stroke-dasharray="" stroke-width="1" /><line x1="60" y1="130" x2="770" y2="130" stroke="#ccc" stroke-dasharray="" stroke-width="1" /><line x1="60" y1="25" x2="770" y2="25" stroke="#ccc" stroke-dasharray="" stroke-width="1" />
    
    <line x1="415" y1="445" x2="415" y2="25" stroke="#333" stroke-dasharray="" stroke-width="2" />
<line x1="400" y1="445" x2="430" y2="445" stroke="#333" stroke-dasharray="" stroke-width="2" />
<line x1="400" y1="25" x2="430" y2="25" stroke="#333" stroke-dasharray="" stroke-width="2" />
<rect x="385" y="235" width="60" height="105" fill="#3498db" fill-opacity="1" stroke="#333" stroke-width="2" rx="0" ry="0"><title>20 / 40 / 50 / 60 / 100</title></rect>
<line x1="385" y1="287.5" x2="445" y2="287.5" stroke="#333" stroke-dasharray="" stroke-width="2" />
<text x="415" y="580" font-family="arial" font-size="14" fill="#333" stroke="none" stroke-width="0" text-anchor="middle" dominant-baseline="alphabetic" alignment-baseline="">Jan</text>
    
</svg>
SVG);
});
