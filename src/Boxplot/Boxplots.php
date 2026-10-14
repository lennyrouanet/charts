<?php

namespace Maantje\Charts\Boxplot;

use Maantje\Charts\Chart;
use Maantje\Charts\Serie;

class Boxplots extends Serie
{
    /**
     * @param  Boxplot[]  $boxplots
     */
    public function __construct(
        private readonly array $boxplots = [],
        public ?string $yAxis = null,
    ) {
        parent::__construct($yAxis);
    }

    public function maxValue(): float
    {
        if (count($this->boxplots) === 0) {
            return 0;
        }

        return max(array_map(fn (Boxplot $boxplot) => $boxplot->maxValue(), $this->boxplots));
    }

    public function minValue(): float
    {
        if (count($this->boxplots) === 0) {
            return 0;
        }

        return min(array_map(fn (Boxplot $boxplot) => $boxplot->minValue(), $this->boxplots));
    }

    public function render(Chart $chart): string
    {
        $numBoxplots = count($this->boxplots);

        if ($numBoxplots === 0) {
            return '';
        }

        $maxWidth = $chart->availableWidth() / $numBoxplots;

        $x = $chart->left();

        $svg = '';

        foreach ($this->boxplots as $boxplot) {
            $svg .= $boxplot->render($chart, $x, $maxWidth);

            $x += $maxWidth;
        }

        return $svg;
    }
}
