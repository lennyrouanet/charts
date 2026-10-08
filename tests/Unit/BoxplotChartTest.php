<?php

use Maantje\Charts\Boxplot\Boxplot;

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
