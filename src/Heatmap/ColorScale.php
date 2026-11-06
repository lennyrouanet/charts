<?php

namespace Maantje\Charts\Heatmap;

use InvalidArgumentException;

class ColorScale
{
    /**
     * @var string[]
     */
    protected array $shades = [];

    public function __construct(string $color, int $shades)
    {
        if ($shades < 1) {
            throw new InvalidArgumentException('A color scale needs at least one shade.');
        }

        if (! preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $color, $matches)) {
            throw new InvalidArgumentException("Invalid hex color: $color");
        }

        $hex = $matches[1];

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        $channels = array_map('hexdec', str_split($hex, 2));

        for ($i = 1; $i <= $shades; $i++) {
            $t = $i / $shades;

            $this->shades[] = '#'.implode('', array_map(
                fn ($channel) => sprintf('%02x', (int) round(255 + ($channel - 255) * $t)),
                $channels,
            ));
        }
    }

    /**
     * @return string[]
     */
    public function shades(): array
    {
        return $this->shades;
    }

    public function colorFor(float $value, float $min, float $max): string
    {
        $count = count($this->shades);

        if ($max == $min) {
            return $this->shades[$count - 1];
        }

        $shade = (int) floor(($value - $min) / ($max - $min) * $count) + 1;

        return $this->shades[min($count, max(1, $shade)) - 1];
    }
}
