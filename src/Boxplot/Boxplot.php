<?php

namespace Maantje\Charts\Boxplot;

use InvalidArgumentException;

class Boxplot
{
    /**
     * @param  float[]  $values  min, Q1, median, Q3, max, then outliers
     */
    public function __construct(
        public string $name,
        public array $values,
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

        for ($i = 1; $i < 5; $i++) {
            if ($this->values[$i] < $this->values[$i - 1]) {
                throw new InvalidArgumentException(sprintf(
                    'Boxplot "%s" values must be ordered: min <= Q1 <= median <= Q3 <= max.',
                    $this->name
                ));
            }
        }
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
