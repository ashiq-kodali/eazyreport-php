<?php

namespace EazyReport\Expressions;

use DateTime;
use DateTimeInterface;

class Formatters
{
    private static array $currencyMap = [
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'INR' => '₹',
        'JPY' => '¥',
        'CNY' => '¥',
        'CAD' => 'CA$',
        'AUD' => 'A$',
        'AED' => 'AED ',
        'SAR' => 'SAR ',
    ];

    public static function escapeHtml(?string $text): string
    {
        return htmlspecialchars($text ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function toDouble(mixed $val): float
    {
        if ($val === null || $val === '') return 0.0;
        if (is_float($val) || is_int($val)) return (float)$val;
        if (is_numeric($val)) return (float)$val;
        $clean = str_replace(',', '', trim((string)$val));
        return is_numeric($clean) ? (float)$clean : 0.0;
    }

    public static function formatCurrency(mixed $val, string $currency = 'USD', int $decimals = 2): string
    {
        $d = self::toDouble($val);
        $curUpper = strtoupper(trim($currency ?: 'USD'));
        $formatted = number_format($d, $decimals, '.', ',');

        if (isset(self::$currencyMap[$curUpper])) {
            return self::$currencyMap[$curUpper] . $formatted;
        }

        return $curUpper . ' ' . $formatted;
    }

    public static function formatNumber(mixed $val, int $decimals = 2, bool $thousands = true): string
    {
        $d = self::toDouble($val);
        return number_format($d, $decimals, '.', $thousands ? ',' : '');
    }

    public static function toDate(mixed $val): ?DateTime
    {
        if ($val === null || $val === '') return null;
        if ($val instanceof DateTime) return $val;
        if ($val instanceof DateTimeInterface) return new DateTime($val->format('Y-m-d H:i:s'));

        try {
            return new DateTime((string)$val);
        } catch (\Exception) {
            return null;
        }
    }

    public static function formatDatePattern(DateTime $dt, string $pattern = 'd/m/Y'): string
    {
        // Convert common .NET / JS patterns to PHP date format if needed
        $phpPattern = strtr($pattern ?: 'd/m/Y', [
            'yyyy' => 'Y',
            'yy' => 'y',
            'MMMM' => 'F',
            'MMM' => 'M',
            'MM' => 'm',
            'M' => 'n',
            'dd' => 'd',
            'd' => 'j',
            'HH' => 'H',
            'hh' => 'h',
            'mm' => 'i',
            'ss' => 's',
            'a' => 'A'
        ]);

        return $dt->format($phpPattern);
    }
}
