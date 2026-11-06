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
        if ($date instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($date)->setTime(0, 0);
        }

        try {
            return (new DateTimeImmutable($date))->setTime(0, 0);
        } catch (Exception) {
            throw new InvalidArgumentException("Invalid date: $date");
        }
    }
}
