<?php

namespace App\Services\Account;

use App\Models\Account;
use App\Models\Address;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class DeleteAddressService
{
    public function delete(Account $account, int $addressId): void
    {
        $address = Address::query()
            ->where('id', $addressId)
            ->where('account_id', $account->id)
            ->firstOrFail();

        if ($address->is_default) {
            throw ValidationException::withMessages([
                'address' => ['Bạn phải đặt địa chỉ khác làm mặc định trước khi xóa địa chỉ này.'],
            ]);
        }

        try {
            $address->delete();
        } catch (Throwable $e) {
            Log::error('Address delete failed', [
                'account_id' => $account->id,
                'address_id' => $addressId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
