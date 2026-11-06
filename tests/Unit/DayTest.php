<?php

use Maantje\Charts\Heatmap\Day;
use Maantje\Charts\Heatmap\DayOfWeek;

it('normalizes string date to midnight', function () {
    $day = new Day(date: '2026-01-03 15:42', value: 4);

    expect($day->date->format('Y-m-d H:i:s'))->toBe('2026-01-03 00:00:00')
        ->and($day->value)->toBe(4.0);
});

it('accepts DateTimeInterface and keeps it immutable', function () {
    $source = new DateTime('2026-01-04 08:00');
    $day = new Day(date: $source, value: 1);
    $source->modify('+1 day');

    expect($day->date->format('Y-m-d H:i'))->toBe('2026-01-04 00:00');
});

it('throws on invalid date string', function () {
    new Day(date: 'not a date', value: 1);
})->throws(InvalidArgumentException::class);

it('maps dates to ISO day of week', function () {
    expect(DayOfWeek::fromDate(new DateTimeImmutable('2026-01-01')))->toBe(DayOfWeek::Thursday)
        ->and(DayOfWeek::fromDate(new DateTimeImmutable('2026-01-04')))->toBe(DayOfWeek::Sunday);
});
