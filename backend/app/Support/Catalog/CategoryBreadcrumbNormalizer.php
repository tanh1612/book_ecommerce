<?php

namespace App\Support\Catalog;

class CategoryBreadcrumbNormalizer
{
    public static function normalize(string $breadcrumb): string
    {
        $normalized = mb_strtolower(trim($breadcrumb));
        $normalized = str_replace(['–', '—', '−'], '-', $normalized);
        $normalized = (string) preg_replace('/\s+/u', ' ', $normalized);
        $normalized = (string) preg_replace('/\s*>\s*/u', ' > ', $normalized);
        $normalized = (string) preg_replace('/\s*-\s*/u', ' - ', $normalized);
        $normalized = (string) preg_replace('/\s+/u', ' ', $normalized);

        return trim($normalized);
    }
}
