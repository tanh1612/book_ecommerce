<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(\Illuminate\Contracts\Auth\Authenticatable::class, \App\Models\Account::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \App\Models\Book::observe(\App\Observers\BookObserver::class);
        \App\Models\BookImage::observe(\App\Observers\BookImageObserver::class);

        \Illuminate\Support\Facades\Storage::extend('cloudinary_fast', function ($app, $config) {
            $cloudinary = new \Cloudinary\Cloudinary($config['url'] ?? env('CLOUDINARY_URL'));
            $adapter = new class($cloudinary) extends \CloudinaryLabs\CloudinaryLaravel\CloudinaryStorageAdapter {
                public function getUrl(string $path): string
                {
                    $cloudName = explode('@', explode(':', env('CLOUDINARY_URL', ''))[2] ?? '')[1] ?? 'dlphkbwji';
                    [$id, $type] = $this->prepareResource($path);
                    return "https://res.cloudinary.com/{$cloudName}/image/upload/{$id}";
                }
            };
            return new \Illuminate\Filesystem\FilesystemAdapter(
                new \League\Flysystem\Filesystem($adapter, $config),
                $adapter,
                $config
            );
        });
    }
}
