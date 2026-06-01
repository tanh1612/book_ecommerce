<?php

namespace App\Providers;

use App\Services\Ai\BookRagSyncDispatcher;
use App\Services\Ai\QueueBookRagSyncService;
use App\Services\Media\BookImageStorageService;
use App\Services\Promotion\FlashSaleScheduleMutex;
use App\Services\Search\BookMeilisearchSyncDispatcher;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Config;
use League\Flysystem\UnableToDeleteFile;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(\Illuminate\Contracts\Auth\Authenticatable::class, \App\Models\Account::class);

        $this->app->bind(
            \Filament\Auth\Notifications\ResetPassword::class,
            \App\Notifications\Auth\AdminPasswordResetNotification::class,
        );

        $this->app->scoped(BookMeilisearchSyncDispatcher::class);
        $this->app->scoped(BookRagSyncDispatcher::class);
        $this->app->scoped(QueueBookRagSyncService::class);

        $this->app->singleton(FlashSaleScheduleMutex::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('ai-chat', function (Request $request): Limit {
            if ($request->user()) {
                return Limit::perMinute((int) config('ai.rate_limits.member_per_minute'))
                    ->by('ai-chat:member:'.$request->user()->id);
            }

            $sessionId = (string) $request->input('session_id', 'missing');

            return Limit::perMinute((int) config('ai.rate_limits.guest_per_minute'))
                ->by('ai-chat:guest:'.$request->ip().'|'.$sessionId);
        });

        RateLimiter::for('ai-chat-feedback', function (Request $request): Limit {
            if ($request->user()) {
                return Limit::perMinute((int) config('ai.rate_limits.feedback_member_per_minute'))
                    ->by('ai-chat-feedback:member:'.$request->user()->id);
            }

            $sessionId = (string) $request->input('session_id', 'missing');

            return Limit::perMinute((int) config('ai.rate_limits.feedback_guest_per_minute'))
                ->by('ai-chat-feedback:guest:'.$request->ip().'|'.$sessionId);
        });

        \App\Models\Book::observe(\App\Observers\BookObserver::class);
        \App\Models\BookImage::observe(\App\Observers\BookImageObserver::class);
        \App\Models\Review::observe(\App\Observers\ReviewObserver::class);
        \App\Models\Category::observe(\App\Observers\CategoryObserver::class);
        \App\Models\Supplier::observe(\App\Observers\SupplierObserver::class);
        \App\Models\Publisher::observe(\App\Observers\PublisherObserver::class);
        \App\Models\Author::observe(\App\Observers\AuthorObserver::class);
        \App\Models\Inventory::observe(\App\Observers\InventoryObserver::class);
        \App\Models\BookDetail::observe(\App\Observers\BookDetailObserver::class);

        \Illuminate\Support\Facades\Storage::extend('cloudinary_fast', function ($app, $config) {
            $cloudinary = new \Cloudinary\Cloudinary($config['url'] ?? env('CLOUDINARY_URL'));
            $adapter = new class($cloudinary) extends \CloudinaryLabs\CloudinaryLaravel\CloudinaryStorageAdapter
            {
                public function getUrl(string $path): string
                {
                    $cloudName = explode('@', explode(':', env('CLOUDINARY_URL', ''))[2] ?? '')[1] ?? 'dlphkbwji';
                    $id = $this->extractPublicId($path);

                    return "https://res.cloudinary.com/{$cloudName}/image/upload/{$id}";
                }

                public function write(string $path, string $contents, Config $config): void
                {
                    $id = $this->extractPublicId($path);
                    $options = app(BookImageStorageService::class)->cloudinaryUploadOptionsForImageAtPath($id);

                    try {
                        Cloudinary::uploadApi()->upload($contents, $options);
                    } catch (Throwable $e) {
                        Log::error('Cloudinary disk write failed', [
                            'public_id' => $id,
                            'error' => $e->getMessage(),
                        ]);

                        throw $e;
                    }
                }

                public function writeStream(string $path, $contents, Config $config): void
                {
                    $id = $this->extractPublicId($path);
                    $options = app(BookImageStorageService::class)->cloudinaryUploadOptionsForImageAtPath($id);

                    try {
                        Cloudinary::uploadApi()->upload($contents, $options);
                    } catch (Throwable $e) {
                        Log::error('Cloudinary disk writeStream failed', [
                            'public_id' => $id,
                            'error' => $e->getMessage(),
                        ]);

                        throw $e;
                    }
                }

                public function delete(string $path): void
                {
                    $id = $this->extractPublicId($path);

                    try {
                        $result = Cloudinary::uploadApi()->destroy($id, ['resource_type' => 'image']);
                        $status = $result['result'] ?? '';

                        if ($status !== 'ok' && $status !== 'not found') {
                            throw UnableToDeleteFile::atLocation($path, (string) ($result['error'] ?? $status));
                        }
                    } catch (Throwable $e) {
                        Log::error('Cloudinary disk delete failed', [
                            'public_id' => $id,
                            'error' => $e->getMessage(),
                        ]);

                        if ($e instanceof UnableToDeleteFile) {
                            throw $e;
                        }

                        throw UnableToDeleteFile::atLocation($path, $e->getMessage(), $e);
                    }
                }

                /**
                 * Extract clean public_id (folder/name, no leading slash, no extension) from a storage path.
                 */
                private function extractPublicId(string $path): string
                {
                    $path = str_replace('\\', '/', $path);
                    $info = pathinfo($path);

                    $dirname = ($info['dirname'] !== '' && $info['dirname'] !== '.') ? $info['dirname'] : '';
                    $id = $dirname !== '' ? $dirname.'/'.$info['filename'] : $info['filename'];

                    return ltrim($id, '/');
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
