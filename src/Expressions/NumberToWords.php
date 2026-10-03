<?php

namespace EazyReport\Expressions;

class NumberToWords
{
    private static array $units = [
        'Zero', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
        'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'
    ];

    private static array $tens = [
        'Zero', 'Ten', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'
    ];

    public static function convert(float|int $number, string $currency = ''): string
    {
        if (is_nan($number) || is_infinite($number)) return 'Zero';

        $abs = abs($number);
        $whole = (int)floor($abs);
        $cents = (int)round(($abs - $whole) * 100);

        $words = $whole === 0 ? 'Zero' : self::numberToWordsHelper($whole);

        if ($number < 0) {
            $words = 'Minus ' . $words;
        }

        if (trim($currency) === '') {
            if ($cents > 0) {
                $words .= sprintf(' and %02d/100', $cents);
            }
            return $words;
        }

        $curUpper = strtoupper(trim($currency));
        $curName = match ($curUpper) {
            'USD', 'CAD', 'AUD' => 'Dollars',
            'EUR' => 'Euros',
            'GBP' => 'Pounds',
            'INR' => 'Rupees',
            default => $curUpper,
        };

        $centName = match ($curUpper) {
            'INR' => 'Paise',
            'GBP' => 'Pence',
            default => 'Cents',
        };

        if ($cents > 0) {
            $words .= " {$curName} and " . self::numberToWordsHelper($cents) . " {$centName}";
        } else {
            $words .= " {$curName} Only";
        }

        return $words;
    }

    private static function numberToWordsHelper(int $number): string
    {
        if ($number === 0) return '';

        if ($number < 20) return self::$units[$number];

        if ($number < 100) {
            $rem = $number % 10;
            return self::$tens[(int)($number / 10)] . ($rem > 0 ? ' ' . self::$units[$rem] : '');
        }

        if ($number < 1000) {
            $rem = $number % 100;
            return self::$units[(int)($number / 100)] . ' Hundred' . ($rem > 0 ? ' ' . self::numberToWordsHelper($rem) : '');
        }

        if ($number < 1000000) {
            $rem = $number % 1000;
            return self::numberToWordsHelper((int)($number / 1000)) . ' Thousand' . ($rem > 0 ? ' ' . self::numberToWordsHelper($rem) : '');
        }

        if ($number < 1000000000) {
            $rem = $number % 1000000;
            return self::numberToWordsHelper((int)($number / 1000000)) . ' Million' . ($rem > 0 ? ' ' . self::numberToWordsHelper($rem) : '');
        }

        $billRem = $number % 1000000000;
        return self::numberToWordsHelper((int)($number / 1000000000)) . ' Billion' . ($billRem > 0 ? ' ' . self::numberToWordsHelper($billRem) : '');
    }
}
