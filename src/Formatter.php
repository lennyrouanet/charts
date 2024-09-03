<?php

namespace Maantje\Charts;

use Closure;
use DateTime;

class Formatter
{
    public static function template(string $template): Closure
    {
        return fn (mixed $value) => str_replace(':value', $value, $template);
    }

    public static function currency(string $locale, string $currency): Closure
    {
        return function (float $value) use ($currency, $locale) {
            $fmt = new \NumberFormatter($locale, \NumberFormatter::CURRENCY );
            return $fmt->formatCurrency($value, $currency);
        };
    }

    public static function timestamp(string $format = 'H:i')
    {
        return fn (int $timestamp) => DateTime::createFromFormat('U', $timestamp)->format($format);
    }
}