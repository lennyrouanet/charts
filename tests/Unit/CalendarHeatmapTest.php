<?php

use Maantje\Charts\Heatmap\CalendarHeatmap;
use Maantje\Charts\Heatmap\Day;
use Maantje\Charts\Heatmap\DayOfWeek;

/**
 * @param  array<string, mixed>  $args
 */
function heatmap(array $args = []): CalendarHeatmap
{
    return new CalendarHeatmap(...array_merge(['dayLabels' => null, 'monthLabels' => null, 'legend' => false], $args));
}

/**
 * @return array<int, array<string, string>>
 */
function heatmapElements(string $svg, string $tag): array
{
    $dom = new DOMDocument;
    $dom->loadXML($svg);

    $elements = [];

    foreach ($dom->getElementsByTagName($tag) as $element) {
        $attributes = ['text' => $element->textContent];

        foreach ($element->attributes ?? [] as $attribute) {
            $attributes[$attribute->name] = $attribute->value;
        }

        $elements[] = $attributes;
    }

    return $elements;
}

/**
 * Cell rects, without the background rect.
 *
 * @return array<int, array<string, string>>
 */
function heatmapCells(string $svg): array
{
    return array_slice(heatmapElements($svg, 'rect'), 1);
}

/**
 * @return array<string, string>
 */
function heatmapRoot(string $svg): array
{
    return heatmapElements($svg, 'svg')[0];
}

$reference = [
    'start' => '2026-01-01',
    'end' => '2026-01-14',
    'days' => ['2026-01-01' => 0, '2026-01-02' => 10, '2026-01-03' => 5],
];

it('renders grid cells for a range', function () use ($reference) {
    $svg = heatmap($reference)->render();
    $cells = heatmapCells($svg);

    expect(heatmapRoot($svg))->toMatchArray(['width' => '42', 'height' => '102'])
        ->and($cells)->toHaveCount(14)
        ->and($cells[0])->toMatchArray(['x' => '0', 'y' => '45', 'fill' => '#c8dbce', 'width' => '12', 'height' => '12', 'rx' => '2', 'ry' => '2'])
        ->and($cells[1])->toMatchArray(['x' => '0', 'y' => '60', 'fill' => '#216e39'])
        ->and($cells[2])->toMatchArray(['x' => '0', 'y' => '75', 'fill' => '#59926b'])
        ->and($cells[3])->toMatchArray(['x' => '0', 'y' => '90', 'fill' => '#ebedf0'])
        ->and($cells[13])->toMatchArray(['x' => '30', 'y' => '30', 'fill' => '#ebedf0']);
});

it('renders the base grid snapshot', function () use ($reference) {
    expect(pretty(heatmap($reference)->render()))->toBe(<<<'SVG'
        <svg xmlns="http://www.w3.org/2000/svg" width="42" height="102">
          <rect x="0" y="0" width="42" height="102" fill="white" fill-opacity="1" stroke="none" stroke-width="0" rx="0" ry="0"/>
          <rect x="0" y="45" width="12" height="12" fill="#c8dbce" fill-opacity="1" stroke="none" stroke-width="0" rx="2" ry="2"/>
          <rect x="0" y="60" width="12" height="12" fill="#216e39" fill-opacity="1" stroke="none" stroke-width="0" rx="2" ry="2"/>
          <rect x="0" y="75" width="12" height="12" fill="#59926b" fill-opacity="1" stroke="none" stroke-width="0" rx="2" ry="2"/>
          <rect x="0" y="90" width="12" height="12" fill="#ebedf0" fill-opacity="1" stroke="none" stroke-width="0" rx="2" ry="2"/>
          <rect x="15" y="0" width="12" height="12" fill="#ebedf0" fill-opacity="1" stroke="none" stroke-width="0" rx="2" ry="2"/>
          <rect x="15" y="15" width="12" height="12" fill="#ebedf0" fill-opacity="1" stroke="none" stroke-width="0" rx="2" ry="2"/>
          <rect x="15" y="30" width="12" height="12" fill="#ebedf0" fill-opacity="1" stroke="none" stroke-width="0" rx="2" ry="2"/>
          <rect x="15" y="45" width="12" height="12" fill="#ebedf0" fill-opacity="1" stroke="none" stroke-width="0" rx="2" ry="2"/>
          <rect x="15" y="60" width="12" height="12" fill="#ebedf0" fill-opacity="1" stroke="none" stroke-width="0" rx="2" ry="2"/>
          <rect x="15" y="75" width="12" height="12" fill="#ebedf0" fill-opacity="1" stroke="none" stroke-width="0" rx="2" ry="2"/>
          <rect x="15" y="90" width="12" height="12" fill="#ebedf0" fill-opacity="1" stroke="none" stroke-width="0" rx="2" ry="2"/>
          <rect x="30" y="0" width="12" height="12" fill="#ebedf0" fill-opacity="1" stroke="none" stroke-width="0" rx="2" ry="2"/>
          <rect x="30" y="15" width="12" height="12" fill="#ebedf0" fill-opacity="1" stroke="none" stroke-width="0" rx="2" ry="2"/>
          <rect x="30" y="30" width="12" height="12" fill="#ebedf0" fill-opacity="1" stroke="none" stroke-width="0" rx="2" ry="2"/>
        </svg>
        SVG
    );
});

it('starts weeks on sunday', function () use ($reference) {
    $svg = heatmap([...$reference, 'weekStart' => DayOfWeek::Sunday])->render();
    $cells = heatmapCells($svg);

    expect(heatmapRoot($svg)['width'])->toBe('42')
        ->and($cells[0])->toMatchArray(['x' => '0', 'y' => '60'])
        ->and($cells[3])->toMatchArray(['x' => '15', 'y' => '0'])
        ->and($cells[13])->toMatchArray(['x' => '30', 'y' => '45']);
});

it('renders only days listed in dayOrder', function () use ($reference) {
    $svg = heatmap([...$reference, 'dayOrder' => [
        DayOfWeek::Monday, DayOfWeek::Tuesday, DayOfWeek::Wednesday, DayOfWeek::Thursday, DayOfWeek::Friday,
    ]])->render();
    $cells = heatmapCells($svg);

    expect(heatmapRoot($svg)['height'])->toBe('72')
        ->and($cells)->toHaveCount(10)
        ->and($cells[2])->toMatchArray(['x' => '15', 'y' => '0']);
});

it('accepts a custom non-cyclic dayOrder', function () use ($reference) {
    $cells = heatmapCells(heatmap([...$reference, 'dayOrder' => [DayOfWeek::Wednesday, DayOfWeek::Monday]])->render());

    expect($cells)->toHaveCount(4)
        ->and($cells[0])->toMatchArray(['y' => '15']) // Monday 2026-01-05
        ->and($cells[1])->toMatchArray(['y' => '0']);  // Wednesday 2026-01-07
});

it('infers range from data', function () {
    $cells = heatmapCells(heatmap(['days' => ['2026-01-05' => 1, '2026-01-07' => 2]])->render());

    expect($cells)->toHaveCount(3);
});

it('ignores data outside the range', function () use ($reference) {
    $cells = heatmapCells(heatmap([...$reference, 'days' => [...$reference['days'], '2026-02-01' => 1000]])->render());

    expect($cells[1]['fill'])->toBe('#216e39')
        ->and($cells[2]['fill'])->toBe('#59926b');
});

it('sums duplicate dates', function () use ($reference) {
    $cells = heatmapCells(heatmap([...$reference, 'days' => [
        new Day('2026-01-02', 4),
        new Day('2026-01-02', 6),
        '2026-01-03' => 10,
    ]])->render());

    expect($cells[1]['fill'])->toBe('#216e39')
        ->and($cells[2]['fill'])->toBe('#216e39');
});

it('uses min and max overrides', function () use ($reference) {
    $cells = heatmapCells(heatmap([...$reference, 'days' => ['2026-01-02' => 10], 'min' => 0, 'max' => 100])->render());

    expect($cells[1]['fill'])->toBe('#c8dbce');
});

it('renders a single-day range', function () {
    $svg = heatmap(['start' => '2026-01-01', 'end' => '2026-01-01'])->render();

    expect(heatmapCells($svg))->toHaveCount(1)
        ->and(heatmapRoot($svg)['width'])->toBe('12');
});

it('uses darkest shade when all values equal', function () use ($reference) {
    $cells = heatmapCells(heatmap([...$reference, 'days' => ['2026-01-02' => 3]])->render());

    expect($cells[1]['fill'])->toBe('#216e39');
});

it('renders an empty range in empty color', function () use ($reference) {
    $cells = heatmapCells(heatmap([...$reference, 'days' => []])->render());

    expect($cells)->toHaveCount(14)
        ->and(array_unique(array_column($cells, 'fill')))->toBe(['#ebedf0']);
});

it('renders background only when nothing is known', function () {
    expect(pretty((new CalendarHeatmap)->render()))->toBe(<<<'SVG'
        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12">
          <rect x="0" y="0" width="12" height="12" fill="white" fill-opacity="1" stroke="none" stroke-width="0" rx="0" ry="0"/>
        </svg>
        SVG
    );
});

it('omits background when null', function () use ($reference) {
    expect(heatmapElements(heatmap([...$reference, 'background' => null])->render(), 'rect'))->toHaveCount(14);
});

it('is not shifted by daylight saving time', function () {
    $timezone = date_default_timezone_get();
    date_default_timezone_set('Europe/Paris');

    try {
        $cells = heatmapCells(heatmap(['start' => '2026-03-23', 'end' => '2026-04-05'])->render());
    } finally {
        date_default_timezone_set($timezone);
    }

    expect($cells[6])->toMatchArray(['x' => '0', 'y' => '90'])  // 2026-03-29
        ->and($cells[7])->toMatchArray(['x' => '15', 'y' => '0']); // 2026-03-30
});

it('spans years and leap days', function () {
    $svg = heatmap(['start' => '2027-12-27', 'end' => '2028-03-05', 'days' => ['2028-02-29' => 1]])->render();
    $cells = heatmapCells($svg);

    expect($cells)->toHaveCount(70)
        ->and(heatmapRoot($svg)['width'])->toBe((string) (10 * 15 - 3))
        ->and($cells[64]['fill'])->toBe('#216e39'); // 2028-02-29
});

it('rejects invalid configuration', function (array $args) {
    heatmap($args);
})->with([
    'weekStart and dayOrder' => [['weekStart' => DayOfWeek::Sunday, 'dayOrder' => [DayOfWeek::Monday]]],
    'empty dayOrder' => [['dayOrder' => []]],
    'duplicate dayOrder' => [['dayOrder' => [DayOfWeek::Monday, DayOfWeek::Monday]]],
    'start after end' => [['start' => '2026-02-01', 'end' => '2026-01-01']],
    'invalid day value' => [['days' => ['2026-01-01' => 'many']]],
    'dayLabels length' => [['dayLabels' => ['a']]],
])->throws(InvalidArgumentException::class);
