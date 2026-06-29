<?php

namespace App\Support;

class Money
{
    /**
     * Formatea un monto en centavos a string legible según la moneda.
     * Ej: Money::format(-123456, 'ARS') => "-$1.234,56"
     */
    public static function format(int $cents, string $currency = 'ARS'): string
    {
        $config = config("budget.currencies.{$currency}", ['symbol' => '$', 'decimals' => 2]);
        $decimals = $config['decimals'] ?? 2;
        $symbol = $config['symbol'] ?? '$';

        $value = $cents / (10 ** $decimals);
        $sign = $value < 0 ? '-' : '';

        // Formato es-AR: punto para miles, coma para decimales.
        $formatted = number_format(abs($value), $decimals, ',', '.');

        return "{$sign}{$symbol}{$formatted}";
    }

    /**
     * Convierte un input de usuario ("1.234,56" o "1234.56") a centavos.
     */
    public static function toCents(string|float|int $amount, int $decimals = 2): int
    {
        if (is_string($amount)) {
            $amount = trim($amount);
            // Normaliza formato es-AR "1.234,56" -> "1234.56"
            if (str_contains($amount, ',')) {
                $amount = str_replace('.', '', $amount);
                $amount = str_replace(',', '.', $amount);
            }
            $amount = (float) $amount;
        }

        return (int) round($amount * (10 ** $decimals));
    }
}
