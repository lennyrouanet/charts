<?php

namespace Maantje\Charts;

use Closure;
use Maantje\Charts\Annotations\YAxisAnnotation;

class YAxis implements Renderable
{
    public Closure $formatter;

    /**
     * @param  array<int, YAxisAnnotation&Renderable>  $annotations
     */
    public function __construct(
        public string $name = 'default',
        public string $title = '',
        public string $color = 'black',
        public ?float $minValue = null,
        public ?float $maxValue = null,
        public int $labelMargin = 0,
        public array $annotations = [],
        public int $characterSize = 5,
        ?Closure $formatter = null
    ) {
        $this->formatter = $formatter ?? fn (mixed $label) => number_format($label);
        $this->annotations = array_map(function (YAxisAnnotation $annotation) {
            $annotation->setYAxis($this->name);

            return $annotation;
        }, $this->annotations);
    }

    public function render(Chart $chart): string
    {
        $numLines = $chart->grid->lines;
        $lineSpacing = $chart->availableHeight() / $numLines;
        $svg = '';

        $titleMargin = 35;
        $labelWidth = strlen($this->formatter->call($this, $chart->maxValue($this->name))) * $this->characterSize + $this->labelMargin;

        $chart->incrementLeftMargin($labelWidth + $titleMargin);

        $maxValue = $chart->maxValue($this->name) / $numLines;

        for ($i = 0; $i <= $numLines; $i++) {
            $value = $maxValue * $i;

            $labelText = $this->formatter->call($this, $value);
            $labelX = $chart->left() - 10;
            $labelY = $chart->top() + $chart->availableHeight() - ($i * $lineSpacing) + 5;

            $svg .= <<<SVG
            <text x="$labelX" y="$labelY" font-family="$chart->fontFamily" font-size="$chart->fontSize" fill="$this->color" text-anchor="end">$labelText</text>
            SVG;
        }

        $titleY = ($chart->availableHeight()) / 2;
        $titleX = $chart->left() - $labelWidth - 25;

        $svg .= <<<SVG
            <text text-anchor="middle" font-family="$chart->fontFamily" alignment-baseline="middle" transform="rotate(270, $titleX, $titleY)" x="$titleX" y="$titleY" font-size="$chart->fontSize" fill="$this->color">$this->title</text>
            SVG;

        return $svg;
    }
}
