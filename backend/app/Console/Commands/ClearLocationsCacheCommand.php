<?php

namespace App\Console\Commands;

use App\Services\Location\LocationService;
use Illuminate\Console\Command;

class ClearLocationsCacheCommand extends Command
{
    protected $signature = 'locations:clear-cache';

    protected $description = 'Invalidate cached administrative location data from the third-party API';

    public function handle(LocationService $locationService): int
    {
        $locationService->invalidateCaches();
        $this->info('Location caches invalidated.');

        return self::SUCCESS;
    }
}
