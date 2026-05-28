<?php

namespace App\Services\Recommendation;

use App\Enums\Recommendation\BookInteractionType;
use App\Models\Account;
use App\Models\Book;
use App\Models\BookInteractionEvent;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PDOException;
use Throwable;

class InteractionTrackingService
{
    private const VIEW_LOCK_SECONDS = 10;

    public function trackView(Account $account, Book $book, ?string $source = null): bool
    {
        $lockKey = $this->viewLockKey((int) $account->id, (int) $book->id);

        try {
            return Cache::lock($lockKey, self::VIEW_LOCK_SECONDS)->block(
                self::VIEW_LOCK_SECONDS,
                fn (): bool => $this->recordViewEvent($account, $book, $source),
            );
        } catch (LockTimeoutException $e) {
            $this->logViewSkippedBecauseLockUnavailable($account, $book, $source, $lockKey, $e);

            return false;
        } catch (Throwable $e) {
            if ($this->isDatabaseException($e)) {
                Log::error('Track view interaction failed', [
                    'account_id' => $account->id,
                    'book_id' => $book->id,
                    'source' => $source,
                    'event_type' => BookInteractionType::View->value,
                    'error' => $e->getMessage(),
                ]);

                throw $e;
            }

            $this->logViewSkippedBecauseLockUnavailable($account, $book, $source, $lockKey, $e);

            return false;
        }
    }

    private function recordViewEvent(Account $account, Book $book, ?string $source): bool
    {
        $dedupMinutes = max((int) config('recommendation.view_deduplication_minutes', 30), 0);
        $windowStart = Carbon::now()->subMinutes($dedupMinutes);

        try {
            return DB::transaction(function () use ($account, $book, $source, $windowStart): bool {
                $alreadyTracked = BookInteractionEvent::query()
                    ->where('account_id', $account->id)
                    ->where('book_id', $book->id)
                    ->where('event_type', BookInteractionType::View)
                    ->where('created_at', '>=', $windowStart)
                    ->exists();

                if ($alreadyTracked) {
                    return false;
                }

                BookInteractionEvent::query()->create([
                    'account_id' => $account->id,
                    'book_id' => $book->id,
                    'event_type' => BookInteractionType::View,
                    'source' => $source,
                    'created_at' => now(),
                ]);

                return true;
            });
        } catch (Throwable $e) {
            Log::error('Track view interaction failed', [
                'account_id' => $account->id,
                'book_id' => $book->id,
                'source' => $source,
                'event_type' => BookInteractionType::View->value,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function logViewSkippedBecauseLockUnavailable(
        Account $account,
        Book $book,
        ?string $source,
        string $lockKey,
        Throwable $exception,
    ): void {
        Log::warning('Track view interaction skipped: cache lock unavailable', [
            'account_id' => $account->id,
            'book_id' => $book->id,
            'source' => $source,
            'lock_key' => $lockKey,
            'error' => $exception->getMessage(),
        ]);
    }

    private function isDatabaseException(Throwable $exception): bool
    {
        $current = $exception;

        while ($current !== null) {
            if ($current instanceof QueryException || $current instanceof PDOException) {
                return true;
            }

            $current = $current->getPrevious();
        }

        return false;
    }

    private function viewLockKey(int $accountId, int $bookId): string
    {
        return sprintf('reco:track:view:%d:%d', $accountId, $bookId);
    }
}
