<?php

namespace Maantje\Charts\Annotations\YAxis;

use Maantje\Charts\Annotations\HasYAxis;
use Maantje\Charts\Annotations\RendersAfterSeries;
use Maantje\Charts\Annotations\YAxisAnnotation;
use Maantje\Charts\Chart;
use Maantje\Charts\Renderable;
use Maantje\Charts\SVG\Fragment;
use Maantje\Charts\SVG\Line;
use Maantje\Charts\SVG\Text;

class YAxisLineAnnotation implements Renderable, RendersAfterSeries, YAxisAnnotation
{
    use HasYAxis;

    public function __construct(
        public float $y,
        public ?string $yAxis = null,
        public string $color = 'yellow',
        public int $size = 2,
        public ?int $fontSize = null,
        public string $dash = '',
        public string $label = '',
        public string $labelColor = '',
    ) {
        //
    }

    public function render(Chart $chart): string
    {
        $y = $chart->yForAxis($this->y, $this->yAxis);

        $labelY = $y - $this->size + 20;

        $labelColor = $this->labelColor ?: $this->color;
        $fontSize = $this->fontSize ?? $chart->fontSize;

        return new Fragment([
            new Line(
                x1: $chart->left(),
                y1: $y,
                x2: $chart->right(),
                y2: $y,
                strokeDashArray: $this->dash,
                stroke: $this->color,
                strokeWidth: $this->size,
            ),
            new Text(
                content: $this->label,
                x: $chart->left() + 5,
                y: $labelY,
                fontFamily: $chart->fontFamily,
                fontSize: $fontSize,
                fill: $labelColor,
                textAnchor: 'start'
            ),
        ]);
    }
}
