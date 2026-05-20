<?php

namespace App\Support;

class VietnameseAmountInWords
{
    private const UNITS = ['', 'một', 'hai', 'ba', 'bốn', 'năm', 'sáu', 'bảy', 'tám', 'chín'];

    private const MAGNITUDES = ['', 'nghìn', 'triệu', 'tỷ'];

    public static function convert(int|float $amount): string
    {
        $value = (int) round((float) $amount);

        if ($value === 0) {
            return 'Không đồng';
        }

        if ($value < 0) {
            return 'Âm '.self::convert(abs($value));
        }

        $chunks = [];
        while ($value > 0) {
            $chunks[] = $value % 1000;
            $value = intdiv($value, 1000);
        }

        $parts = [];
        foreach ($chunks as $index => $chunk) {
            if ($chunk === 0) {
                continue;
            }

            $chunkText = self::readThreeDigits($chunk, $index > 0);
            $magnitude = self::MAGNITUDES[$index] ?? '';
            $parts[] = trim($chunkText.' '.$magnitude);
        }

        $text = implode(' ', array_reverse($parts));

        return ucfirst($text).' đồng';
    }

    private static function readThreeDigits(int $number, bool $fullHundreds): string
    {
        $hundreds = intdiv($number, 100);
        $tens = intdiv($number % 100, 10);
        $units = $number % 10;

        $parts = [];

        if ($hundreds > 0) {
            $parts[] = self::UNITS[$hundreds].' trăm';
        } elseif ($fullHundreds && ($tens > 0 || $units > 0)) {
            $parts[] = 'không trăm';
        }

        if ($tens > 1) {
            $parts[] = self::UNITS[$tens].' mươi';
            if ($units === 1) {
                $parts[] = 'mốt';
            } elseif ($units === 5) {
                $parts[] = 'lăm';
            } elseif ($units > 0) {
                $parts[] = self::UNITS[$units];
            }
        } elseif ($tens === 1) {
            $parts[] = 'mười';
            if ($units === 5) {
                $parts[] = 'lăm';
            } elseif ($units > 0) {
                $parts[] = self::UNITS[$units];
            }
        } elseif ($tens === 0 && $units > 0) {
            if ($hundreds > 0 || $fullHundreds) {
                $parts[] = 'lẻ';
            }
            if ($units === 5 && ($hundreds > 0 || $fullHundreds)) {
                $parts[] = 'năm';
            } else {
                $parts[] = self::UNITS[$units];
            }
        }

        return implode(' ', $parts);
    }
}
