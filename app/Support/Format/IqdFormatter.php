<?php

declare(strict_types=1);

namespace App\Support\Format;

/**
 * Presentation of IQD figures (Document B §10): no decimals, thousands separators,
 * negatives in parentheses, zero as a dash, and Arabic-Indic digits for a user who
 * prefers them. Screen, PDF and Excel all format through here, so the three never
 * disagree about what a figure looks like.
 */
final class IqdFormatter
{
    private const ARABIC_INDIC = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];

    public static function format(string|int|float|null $amount, string $numerals = 'latn'): string
    {
        if ($amount === null || $amount === '') {
            return '';
        }

        $value = self::normalise((string) $amount);

        if ($value === '0') {
            return '—';
        }

        $negative = str_starts_with($value, '-');
        $digits = ltrim($value, '-');
        $grouped = strrev(implode(',', str_split(strrev($digits), 3)));
        $text = $negative ? '('.$grouped.')' : $grouped;

        return $numerals === 'arab' ? self::toArabicIndic($text) : $text;
    }

    /**
     * Integer string of an amount that may arrive as "1500", "1500.00" or 1500.0.
     * A fractional dinar is not rounded away silently: it is kept visible.
     */
    public static function normalise(string $amount): string
    {
        $amount = trim($amount);

        if (str_contains($amount, '.')) {
            [$whole, $fraction] = explode('.', $amount, 2);

            if (rtrim($fraction, '0') !== '') {
                return $amount;
            }

            $amount = $whole;
        }

        $negative = str_starts_with($amount, '-');
        $digits = ltrim(ltrim($amount, '-+'), '0');

        if ($digits === '') {
            return '0';
        }

        return ($negative ? '-' : '').$digits;
    }

    public static function toArabicIndic(string $text): string
    {
        return strtr($text, array_combine(range('0', '9'), self::ARABIC_INDIC) + [',' => '٬']);
    }
}
