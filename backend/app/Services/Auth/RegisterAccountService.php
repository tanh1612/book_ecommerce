<?php

namespace App\Services\Auth;

use App\Enums\Account\AccountRole;
use App\Models\Account;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class RegisterAccountService
{
    public function __construct(
        private EmailVerificationService $emailVerificationService,
    ) {}

    /**
     * @param  array{email: string, password: string, register_token: string}  $data
     */
    public function register(array $data): Account
    {
        if (! $this->emailVerificationService->validateRegisterToken($data['email'], $data['register_token'])) {
            throw ValidationException::withMessages([
                'register_token' => ['Yêu cầu xác thực email trước.'],
            ]);
        }

        try {
            $account = DB::transaction(function () use ($data): Account {
                $account = Account::create([
                    'email' => $data['email'],
                    'password' => $data['password'],
                    'role' => AccountRole::Customer,
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]);

                $account->profile()->create();

                return $account->load('profile');
            });

            $this->emailVerificationService->deleteRegisterToken($data['email']);

            return $account;
        } catch (Throwable $e) {
            Log::error('Account registration failed', [
                'email' => $data['email'] ?? null,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
