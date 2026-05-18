<?php

namespace App\Filament\Concerns;

use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\QueryException;

trait HandlesBooksPriceCheckConstraintViolation
{
    protected static function isBooksPricesCheckConstraintViolation(QueryException $exception): bool
    {
        if (str_contains($exception->getMessage(), 'books_prices')) {
            return true;
        }

        $driverCode = (int) ($exception->errorInfo[1] ?? 0);

        return $driverCode === 3819 && str_contains($exception->getMessage(), '`books`');
    }

    protected function abortOnBooksPricesCheckConstraint(QueryException $exception): never
    {
        if (! static::isBooksPricesCheckConstraintViolation($exception)) {
            throw $exception;
        }

        Notification::make()
            ->title('Không thể lưu')
            ->body('Giá gốc và giá bán phải lớn hơn 0 (theo ràng buộc dữ liệu).')
            ->danger()
            ->send();

        throw (new Halt)->rollBackDatabaseTransaction();
    }
}
