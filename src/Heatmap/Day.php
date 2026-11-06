<?php

namespace Maantje\Charts\Heatmap;

use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use InvalidArgumentException;

class Day
{
    public readonly DateTimeImmutable $date;

    public function __construct(
        string|DateTimeInterface $date,
        public float $value,
    ) {
        $this->date = self::normalize($date);
    }

    public static function normalize(string|DateTimeInterface $date): DateTimeImmutable
    {
        if (is_string($date)) {
            try {
                $date = new DateTimeImmutable($date);
            } catch (Exception) {
                throw new InvalidArgumentException("Invalid date: $date");
            }
        }

        // Keep the calendar day only, in the default timezone, so dates from any timezone compare as days.
        return new DateTimeImmutable($date->format('Y-m-d'));
    }
}
