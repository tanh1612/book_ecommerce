<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait GeneratesUniqueSlug
{
    protected function generateUniqueSlug(string $name, string $table): string
    {
        $slug = Str::slug($name);
        $original = $slug;
        $counter = 2;

        while (DB::table($table)->where('slug', $slug)->exists()) {
            $slug = "{$original}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
