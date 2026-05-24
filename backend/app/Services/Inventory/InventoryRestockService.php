<?php

namespace App\Services\Inventory;

use App\Models\Inventory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class InventoryRestockService
{
    /**
     * @param  array{
     *     book_id: int|string,
     *     warehouse_id: int|string,
     *     quantity: int|string,
     *     location_code?: string|null,
     *     last_restocked_at?: mixed,
     *     sold_quantity?: int|string|null,
     *     reserved_quantity?: int|string|null,
     * }  $data
     */
    public function createOrRestock(array $data): InventoryRestockResult
    {
        try {
            return DB::transaction(function () use ($data): InventoryRestockResult {
                $bookId = (int) $data['book_id'];
                $warehouseId = (int) $data['warehouse_id'];
                $quantityDelta = max(0, (int) $data['quantity']);
                $locationCode = trim((string) ($data['location_code'] ?? ''));
                $newRestockedAt = $this->parseRestockedAt($data['last_restocked_at'] ?? null);

                /** @var Inventory|null $existing */
                $existing = Inventory::query()
                    ->where('book_id', $bookId)
                    ->lockForUpdate()
                    ->first();

                if ($existing === null) {
                    $this->assertLastRestockedAtNotFuture($newRestockedAt);

                    $inventory = Inventory::query()->create([
                        'book_id' => $bookId,
                        'warehouse_id' => $warehouseId,
                        'quantity' => $quantityDelta,
                        'sold_quantity' => max(0, (int) ($data['sold_quantity'] ?? 0)),
                        'reserved_quantity' => max(0, (int) ($data['reserved_quantity'] ?? 0)),
                        'location_code' => $locationCode,
                        'last_restocked_at' => $newRestockedAt,
                    ]);

                    return new InventoryRestockResult($inventory, false);
                }

                if ($newRestockedAt === null) {
                    throw ValidationException::withMessages([
                        'last_restocked_at' => ['Vui lòng nhập ngày nhập kho khi bổ sung tồn.'],
                    ]);
                }

                $this->assertLastRestockedAtForRestock($existing->last_restocked_at, $newRestockedAt);

                $existing->update([
                    'warehouse_id' => $warehouseId,
                    'quantity' => (int) $existing->quantity + $quantityDelta,
                    'location_code' => $locationCode,
                    'last_restocked_at' => $newRestockedAt,
                ]);

                return new InventoryRestockResult($existing->fresh(), true);
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Inventory create or restock failed', [
                'book_id' => $data['book_id'] ?? null,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function parseRestockedAt(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value);
    }

    private function assertLastRestockedAtNotFuture(?Carbon $restockedAt): void
    {
        if ($restockedAt === null) {
            return;
        }

        if ($restockedAt->gt(now())) {
            throw ValidationException::withMessages([
                'last_restocked_at' => ['Ngày nhập kho không được ở tương lai.'],
            ]);
        }
    }

    private function assertLastRestockedAtForRestock(?Carbon $previous, Carbon $new): void
    {
        if ($new->gt(now())) {
            throw ValidationException::withMessages([
                'last_restocked_at' => ['Ngày nhập kho không được ở tương lai.'],
            ]);
        }

        if ($previous !== null && $new->lt($previous)) {
            throw ValidationException::withMessages([
                'last_restocked_at' => ['Ngày nhập kho mới không được trước lần nhập kho gần nhất.'],
            ]);
        }
    }
}
