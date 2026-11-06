<?php

namespace Maantje\Charts\Heatmap;

use DateTimeInterface;

enum DayOfWeek: int
{
    case Monday = 1;
    case Tuesday = 2;
    case Wednesday = 3;
    case Thursday = 4;
    case Friday = 5;
    case Saturday = 6;
    case Sunday = 7;

    public static function fromDate(DateTimeInterface $date): self
    {
        return self::from((int) $date->format('N'));
    }
}
