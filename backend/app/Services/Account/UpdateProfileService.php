<?php

namespace App\Services\Account;

use App\Models\Account;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class UpdateProfileService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Account $account, array $data): Account
    {
        try {
            DB::transaction(function () use ($account, $data): void {
                $account->profile()->updateOrCreate(
                    ['account_id' => $account->id],
                    $data
                );
            });

            return $account->fresh()->load('profile');
        } catch (Throwable $e) {
            Log::error('Account profile update failed', [
                'account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
