<?php

namespace App\Services\Auth;

use App\Models\Account;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

class LoginAccountService
{
    private const int MAX_FAILED_ATTEMPTS = 5;

    private const int DECAY_SECONDS = 60;

    /**
     * @param  array{email: string, password: string, remember?: bool}  $credentials
     */
    public function login(array $credentials, string $ip): Account
    {
        $email = $credentials['email'];
        $password = $credentials['password'];
        $remember = (bool) ($credentials['remember'] ?? false);
        $key = $this->throttleKey($email, $ip);

        if (RateLimiter::tooManyAttempts($key, self::MAX_FAILED_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($key);

            throw new TooManyRequestsHttpException(
                $seconds,
                "Bạn đã nhập sai quá nhiều lần. Vui lòng thử lại sau {$seconds} giây."
            );
        }

        try {
            $account = Account::query()->where('email', $email)->first();

            if ($account === null || ! Hash::check($password, $account->password)) {
                RateLimiter::hit($key, self::DECAY_SECONDS);

                throw ValidationException::withMessages([
                    'email' => ['Email hoặc mật khẩu không đúng.'],
                ]);
            }

            if (! $account->is_active) {
                throw new HttpException(
                    403,
                    'Tài khoản đã bị khóa hoặc chưa được kích hoạt.'
                );
            }

            if ($account->email_verified_at === null) {
                throw new HttpException(
                    403,
                    'Vui lòng xác thực email trước khi đăng nhập.'
                );
            }

            RateLimiter::clear($key);

            Auth::guard('web')->login($account, $remember);
            request()->session()->migrate(true);
            request()->session()->regenerateToken();

            return $account->load('profile');
        } catch (ValidationException|HttpException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Account login failed', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function throttleKey(string $email, string $ip): string
    {
        return 'auth-login:'.sha1(strtolower($email).'|'.$ip);
    }
}
