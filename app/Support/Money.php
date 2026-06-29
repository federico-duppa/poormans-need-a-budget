<?php

namespace App\Support;

class Money
{
    /**
     * Format an amount in cents to a human-readable string based on the currency.
     * E.g.: Money::format(-123456, 'ARS') => "-$1.234,56"
     */
    public static function format(int $cents, string $currency = 'ARS'): string
    {
        $config = config("budget.currencies.{$currency}", ['symbol' => '$', 'decimals' => 2]);
        $decimals = $config['decimals'] ?? 2;
        $symbol = $config['symbol'] ?? '$';

        $value = $cents / (10 ** $decimals);
        $sign = $value < 0 ? '-' : '';

        // es-AR format: dot for thousands, comma for decimals.
        $formatted = number_format(abs($value), $decimals, ',', '.');

        return "{$sign}{$symbol}{$formatted}";
    }

    /**
     * Convert a user input ("1.234,56" or "1234.56") to cents.
     */
    public static function toCents(string|float|int $amount, int $decimals = 2): int
    {
        if (is_string($amount)) {
            $amount = trim($amount);
            // Normalize es-AR format "1.234,56" -> "1234.56"
            if (str_contains($amount, ',')) {
                $amount = str_replace('.', '', $amount);
                $amount = str_replace(',', '.', $amount);
            }
            $amount = (float) $amount;
        }

        return (int) round($amount * (10 ** $decimals));
    }
}
