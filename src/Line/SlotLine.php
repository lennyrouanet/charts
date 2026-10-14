<?php

namespace Maantje\Charts\Line;

use Maantje\Charts\Chart;
use Maantje\Charts\SVG\Circle;
use Maantje\Charts\SVG\Fragment;
use Maantje\Charts\SVG\Path;

class SlotLine extends Line
{
    /**
     * @param  float[]  $values  one value per slot, in order
     */
    public function __construct(
        public array $values = [],
        int $size = 5,
        ?string $yAxis = null,
        string $color = 'black',
        ?string $areaColor = null,
        ?float $curve = null,
        bool $stepLine = false,
        public ?string $pointColor = null,
        public float $pointSize = 5,
    ) {
        $this->values = array_values($values);

        parent::__construct(
            points: array_map(fn (int $index, float $value) => [$index, $value], array_keys($this->values), $this->values),
            size: $size,
            yAxis: $yAxis,
            color: $color,
            areaColor: $areaColor,
            curve: $curve,
            stepLine: $stepLine,
        );
    }

    public function render(Chart $chart): string
    {
        $numSlots = count($this->values);

        if ($numSlots === 0) {
            return '';
        }

        $slotWidth = $chart->availableWidth() / $numSlots;
        $minY = $chart->yForAxis($chart->minValue($this->yAxis), $this->yAxis);

        $pointsSvg = '';
        $points = [];

        foreach ($this->values as $index => $value) {
            $x = $chart->left() + ($index + 0.5) * $slotWidth;
            $y = $chart->yForAxis($value, $this->yAxis);

            $points[] = [$x, min($y, $minY)];

            if ($this->pointColor !== null) {
                $pointsSvg .= new Circle(
                    cx: $x,
                    cy: $y,
                    r: $this->pointSize,
                    fill: $this->pointColor,
                    title: $value
                );
            }
        }

        return new Fragment([
            $this->areaColor ? new Path(
                d: $this->generateAreaPath($points, $minY),
                fill: $this->areaColor,
                stroke: 'none'
            ) : null,
            new Path(
                d: $this->stepLine ? $this->generateStepPath($points) : $this->generateSmoothPath($points),
                fill: 'none',
                stroke: $this->color,
                strokeWidth: $this->size
            ),
            $pointsSvg,
        ]);
    }

    /**
     * Values are placed by slot, so they give the X axis no scale.
     *
     * @return float[]
     */
    public function xPoints(): array
    {
        return [];
    }

    public function maxYValue(): float
    {
        return count($this->values) === 0 ? 0 : max($this->values);
    }

    public function minYValue(): float
    {
        return count($this->values) === 0 ? 0 : min($this->values);
    }
}
