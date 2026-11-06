<?php

use Maantje\Charts\Heatmap\ColorScale;

it('generates shades from white to the base color', function () {
    expect((new ColorScale('#216e39', 4))->shades())
        ->toBe(['#c8dbce', '#90b79c', '#59926b', '#216e39']);
});

it('accepts short hex and uppercase', function () {
    expect((new ColorScale('#F00', 2))->shades())->toBe(['#ff8080', '#ff0000']);
});

it('maps values linearly to shades', function () {
    $scale = new ColorScale('#216e39', 4);

    expect($scale->colorFor(0, 0, 10))->toBe('#c8dbce')
        ->and($scale->colorFor(5, 0, 10))->toBe('#59926b')
        ->and($scale->colorFor(10, 0, 10))->toBe('#216e39')
        ->and($scale->colorFor(2.4, 0, 10))->toBe('#c8dbce')
        ->and($scale->colorFor(2.5, 0, 10))->toBe('#90b79c');
});

it('handles negative values', function () {
    $scale = new ColorScale('#216e39', 4);

    expect($scale->colorFor(-5, -5, 5))->toBe('#c8dbce')
        ->and($scale->colorFor(0, -5, 5))->toBe('#59926b')
        ->and($scale->colorFor(5, -5, 5))->toBe('#216e39');
});

it('uses the darkest shade when min equals max', function () {
    expect((new ColorScale('#216e39', 4))->colorFor(3, 3, 3))->toBe('#216e39');
});

it('clamps values outside bounds', function () {
    $scale = new ColorScale('#216e39', 4);

    expect($scale->colorFor(-100, 0, 10))->toBe('#c8dbce')
        ->and($scale->colorFor(100, 0, 10))->toBe('#216e39');
});

it('rejects invalid colors', function (string $color) {
    new ColorScale($color, 4);
})->with(['green', '#12', '#1234567', 'rgb(0,0,0)', '#ggg'])->throws(InvalidArgumentException::class);

it('rejects less than one shade', function () {
    new ColorScale('#216e39', 0);
})->throws(InvalidArgumentException::class);
