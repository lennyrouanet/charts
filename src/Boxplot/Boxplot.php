<?php

namespace Maantje\Charts\Boxplot;

use InvalidArgumentException;
use Maantje\Charts\Chart;
use Maantje\Charts\SVG\Circle;
use Maantje\Charts\SVG\Fragment;
use Maantje\Charts\SVG\Line;
use Maantje\Charts\SVG\Rect;
use Maantje\Charts\SVG\Text;

class Boxplot
{
    /** @var array<int, int|float> */
    public readonly array $values;

    /**
     * @param  float[]  $values  min, Q1, median, Q3, max, then outliers
     */
    public function __construct(
        public string $name,
        array $values,
        public ?string $yAxis = null,
        public string $color = '#333',
        public float $strokeWidth = 2,
        public string $fillColor = '#3498db',
        public ?float $width = 60,
        public ?string $medianColor = null,
        public ?string $outlierColor = null,
        public float $outlierSize = 4,
        public string $labelColor = '#333',
        public int $labelMarginY = 30,
    ) {
        $this->values = array_values($values);

        if (count($this->values) < 5) {
            throw new InvalidArgumentException(sprintf(
                'Boxplot "%s" needs at least 5 values (min, Q1, median, Q3, max), %d given.',
                $this->name,
                count($this->values)
            ));
        }

        foreach ($this->values as $value) {
            if (! $this->isFiniteNumber($value)) {
                throw new InvalidArgumentException(sprintf(
                    'Boxplot "%s" values must be finite numbers.',
                    $this->name
                ));
            }
        }

        for ($i = 1; $i < 5; $i++) {
            if ($this->values[$i] < $this->values[$i - 1]) {
                throw new InvalidArgumentException(sprintf(
                    'Boxplot "%s" values must be ordered: min <= Q1 <= median <= Q3 <= max.',
                    $this->name
                ));
            }
        }

        if (! is_null($this->width) && $this->width < 0) {
            throw new InvalidArgumentException(sprintf('Boxplot "%s" width must not be negative.', $this->name));
        }

        if ($this->outlierSize < 0) {
            throw new InvalidArgumentException(sprintf('Boxplot "%s" outlierSize must not be negative.', $this->name));
        }
    }

    public function render(Chart $chart, float $x, float $maxWidth): string
    {
        $width = min($this->width ?? $maxWidth, $maxWidth);
        $x += ($maxWidth - $width) / 2;
        $centerX = $x + $width / 2;
        $capHalf = $width / 4;

        [$min, $q1, $median, $q3, $max] = array_map(
            fn (float $value) => $chart->yForAxis($value, $this->yAxis),
            array_slice($this->values, 0, 5)
        );

        $elements = [
            new Line(
                x1: $centerX,
                y1: $min,
                x2: $centerX,
                y2: $max,
                stroke: $this->color,
                strokeWidth: $this->strokeWidth
            ),
            new Line(
                x1: $centerX - $capHalf,
                y1: $min,
                x2: $centerX + $capHalf,
                y2: $min,
                stroke: $this->color,
                strokeWidth: $this->strokeWidth
            ),
            new Line(
                x1: $centerX - $capHalf,
                y1: $max,
                x2: $centerX + $capHalf,
                y2: $max,
                stroke: $this->color,
                strokeWidth: $this->strokeWidth
            ),
            new Rect(
                x: $x,
                y: $q3,
                width: $width,
                height: $q1 - $q3,
                fill: $this->fillColor,
                stroke: $this->color,
                strokeWidth: $this->strokeWidth,
                title: implode(' / ', array_slice($this->values, 0, 5))
            ),
            new Line(
                x1: $x,
                y1: $median,
                x2: $x + $width,
                y2: $median,
                stroke: $this->medianColor ?? $this->color,
                strokeWidth: $this->strokeWidth
            ),
        ];

        foreach (array_slice($this->values, 5) as $outlier) {
            $elements[] = new Circle(
                cx: $centerX,
                cy: $chart->yForAxis($outlier, $this->yAxis),
                r: $this->outlierSize,
                fill: $this->outlierColor ?? $this->color,
                title: $outlier
            );
        }

        if ($this->name !== '') {
            $elements[] = new Text(
                content: $this->name,
                x: $centerX,
                y: $chart->bottom() + $this->labelMarginY,
                fontFamily: $chart->fontFamily,
                fontSize: $chart->fontSize,
                fill: $this->labelColor,
                textAnchor: 'middle'
            );
        }

        return new Fragment($elements);
    }

    protected function isFiniteNumber(mixed $value): bool
    {
        return (is_int($value) || is_float($value)) && is_finite($value);
    }

    public function minValue(): float
    {
        return min($this->values);
    }

    public function maxValue(): float
    {
        return max($this->values);
    }
}
