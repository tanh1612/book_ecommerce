<?php

namespace App\Services\Ai\Support;

class IntentTextNormalizer
{
    public static function normalize(string $text): string
    {
        $text = VietnameseAccentFolder::fold(mb_strtolower(trim($text)));
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text) ?? $text;

        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }
}
