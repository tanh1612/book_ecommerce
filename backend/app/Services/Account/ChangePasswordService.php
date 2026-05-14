<?php

namespace App\Services\Account;

use App\Models\Account;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class ChangePasswordService
{
    /**
     * @param  array{current_password: string, password: string}  $data
     */
    public function change(Account $account, array $data): void
    {
        try {
            if (! Hash::check($data['current_password'], $account->password)) {
                throw ValidationException::withMessages([
                    'current_password' => ['Mật khẩu hiện tại không đúng.'],
                ]);
            }

            if (Hash::check($data['password'], $account->password)) {
                throw ValidationException::withMessages([
                    'password' => ['Mật khẩu mới không được trùng với mật khẩu hiện tại.'],
                ]);
            }

            DB::transaction(function () use ($account, $data): void {
                $account->password = $data['password'];
                $account->remember_token = Str::random(60);
                $account->save();
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Account change password failed', [
                'account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
