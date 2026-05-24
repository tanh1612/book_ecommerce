<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait GeneratesUniqueSlug
{
    /**
     * Build URL slug from a display name; treats /, \, and | as word separators.
     */
    public static function slugFromName(?string $name): string
    {
        if (blank($name)) {
            return '';
        }

        $normalized = preg_replace('#[/\\\\|]+#u', ' ', $name) ?? $name;
        $normalized = preg_replace('#\s+#u', ' ', trim($normalized)) ?? $normalized;

        return Str::slug($normalized);
    }

    protected function generateUniqueSlug(string $name, string $table): string
    {
        $slug = static::slugFromName($name);
        $original = $slug;
        $counter = 2;

        while (DB::table($table)->where('slug', $slug)->exists()) {
            $slug = "{$original}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
