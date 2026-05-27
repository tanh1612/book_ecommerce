<?php

namespace App\Services\Promotion;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class FlashSaleScheduleMutex
{
    private const LOCK_NAME = 'bookify_flash_sale_schedule';

    private const TIMEOUT_SECONDS = 15;

    private int $holdDepth = 0;

    /**
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public function runExclusive(callable $callback, bool $nested = false): mixed
    {
        $execute = function () use ($callback): mixed {
            $this->acquire();

            try {
                return $callback();
            } finally {
                $this->release();
            }
        };

        if ($nested) {
            return $execute();
        }

        $this->acquire();

        try {
            return DB::transaction($callback);
        } finally {
            $this->release();
        }
    }

    public function acquire(): void
    {
        if ($this->holdDepth > 0) {
            $this->holdDepth++;

            return;
        }

        try {
            $result = DB::selectOne('SELECT GET_LOCK(?, ?) AS acquired', [
                self::LOCK_NAME,
                self::TIMEOUT_SECONDS,
            ]);

            if ((int) ($result->acquired ?? 0) !== 1) {
                throw ValidationException::withMessages([
                    'start_at' => ['Hệ thống đang xử lý lịch Flash Sale. Vui lòng thử lại sau giây lát.'],
                ]);
            }

            $this->holdDepth = 1;
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('Flash sale schedule mutex acquire failed', [
                'error' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);

            throw $exception;
        }
    }

    public function release(): void
    {
        if ($this->holdDepth === 0) {
            return;
        }

        if ($this->holdDepth > 1) {
            $this->holdDepth--;

            return;
        }

        try {
            DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [self::LOCK_NAME]);
        } catch (Throwable $exception) {
            Log::warning('Flash sale schedule mutex release failed', [
                'error' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);
        } finally {
            $this->holdDepth = 0;
        }
    }
}
