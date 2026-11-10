<?php

namespace App\Support;

use InvalidArgumentException;

class Pkr
{
    public static function format(string $amount): string
    {
        if (! preg_match('/^\d{1,16}(?:\.\d{1,2})?$/D', $amount)) {
            throw new InvalidArgumentException('Expected an exact non-negative PKR decimal string.');
        }
        [$whole, $fraction] = array_pad(explode('.', $amount), 2, '');

        return 'PKR '.preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $whole).'.'.str_pad($fraction, 2, '0');
    }

    public static function formatSigned(string $amount): string
    {
        if (! preg_match('/^-?\d{1,16}(?:\.\d{1,2})?$/D', $amount)) {
            throw new InvalidArgumentException('Expected an exact signed PKR decimal string.');
        }
        $negative = str_starts_with($amount, '-');
        $magnitude = $negative ? substr($amount, 1) : $amount;
        $negative = $negative && preg_match('/^0(?:\.0{1,2})?$/D', $magnitude) !== 1;

        return ($negative ? '-' : '').self::format($magnitude);
    }
}
