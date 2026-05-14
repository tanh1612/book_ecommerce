<?php

namespace App\Services\Auth;

use App\Mail\PasswordResetOtpMail;
use App\Models\Account;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class PasswordResetService
{
    private const OTP_PREFIX = 'otp:password-reset:';

    private const COOLDOWN_PREFIX = 'otp:cooldown:password-reset:';

    private const RESET_TOKEN_PREFIX = 'password-reset:token:';

    private const OTP_ATTEMPT_PREFIX = 'password-reset:otp-attempts:';

    private const OTP_TTL_SECONDS = 300;

    private const COOLDOWN_TTL_SECONDS = 60;

    private const RESET_TOKEN_TTL_SECONDS = 600;

    private const MAX_OTP_ATTEMPTS = 5;

    private function store(): Repository
    {
        return Cache::store(config('registration.cache_store', 'redis'));
    }

    private function normalizedEmailKey(string $email): string
    {
        return strtolower(trim($email));
    }

    public function sendOtp(string $email): void
    {
        $keyEmail = $this->normalizedEmailKey($email);

        try {
            $account = $this->findActiveAccountByEmail($keyEmail);

            if ($account === null) {
                return;
            }

            $cooldownKey = self::COOLDOWN_PREFIX.$keyEmail;

            if ($this->store()->has($cooldownKey)) {
                throw ValidationException::withMessages([
                    'email' => ['Gửi mã quá nhanh. Vui lòng thử lại sau 60 giây.'],
                ]);
            }

            $otp = (string) random_int(100_000, 999_999);
            $otpKey = self::OTP_PREFIX.$keyEmail;

            $this->store()->forget($this->otpAttemptKey($keyEmail));
            $this->store()->put($otpKey, $otp, self::OTP_TTL_SECONDS);
            $this->store()->put($cooldownKey, '1', self::COOLDOWN_TTL_SECONDS);

            try {
                Mail::to($account->email)->send(new PasswordResetOtpMail($otp));
            } catch (Throwable $e) {
                $this->store()->forget($otpKey);
                $this->store()->forget($cooldownKey);
                Log::error('Failed to send password reset OTP email', [
                    'email' => $email,
                    'error' => $e->getMessage(),
                ]);

                throw $e;
            }
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Password reset OTP send failed', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * @throws ValidationException
     */
    public function verifyOtp(string $email, string $otp): string
    {
        $keyEmail = $this->normalizedEmailKey($email);
        $otpKey = self::OTP_PREFIX.$keyEmail;
        $attemptKey = $this->otpAttemptKey($keyEmail);

        try {
            $stored = $this->store()->get($otpKey);

            if ($stored === null) {
                throw ValidationException::withMessages([
                    'otp' => ['Mã xác nhận không đúng hoặc đã hết hạn.'],
                ]);
            }

            if (! hash_equals((string) $stored, $otp)) {
                $attempts = (int) ($this->store()->get($attemptKey) ?? 0) + 1;
                $this->store()->put($attemptKey, $attempts, self::OTP_TTL_SECONDS);

                if ($attempts >= self::MAX_OTP_ATTEMPTS) {
                    $this->store()->forget($otpKey);
                    $this->store()->forget($attemptKey);

                    throw ValidationException::withMessages([
                        'otp' => ['Bạn đã nhập sai quá nhiều lần. Vui lòng yêu cầu gửi lại mã.'],
                    ]);
                }

                throw ValidationException::withMessages([
                    'otp' => ['Mã xác nhận không đúng hoặc đã hết hạn.'],
                ]);
            }

            $this->store()->forget($otpKey);
            $this->store()->forget($attemptKey);

            $resetToken = Str::random(60);
            $tokenKey = self::RESET_TOKEN_PREFIX.$keyEmail;
            $this->store()->put($tokenKey, $resetToken, self::RESET_TOKEN_TTL_SECONDS);

            return $resetToken;
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Password reset OTP verify failed', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * @param  array{email: string, reset_token: string, password: string}  $data
     */
    public function resetPassword(array $data): void
    {
        $keyEmail = $this->normalizedEmailKey($data['email']);
        $tokenKey = self::RESET_TOKEN_PREFIX.$keyEmail;

        try {
            $stored = $this->store()->get($tokenKey);

            if ($stored === null || ! hash_equals((string) $stored, $data['reset_token'])) {
                throw ValidationException::withMessages([
                    'reset_token' => ['Mã đặt lại mật khẩu không hợp lệ hoặc đã hết hạn.'],
                ]);
            }

            $account = $this->findActiveAccountByEmail($keyEmail);

            if ($account === null) {
                $this->store()->forget($tokenKey);

                throw ValidationException::withMessages([
                    'reset_token' => ['Mã đặt lại mật khẩu không hợp lệ hoặc đã hết hạn.'],
                ]);
            }

            try {
                DB::transaction(function () use ($account, $data): void {
                    $account->password = $data['password'];
                    $account->remember_token = Str::random(60);
                    $account->save();
                });
            } catch (Throwable $e) {
                Log::error('Password reset database update failed', [
                    'account_id' => $account->id,
                    'error' => $e->getMessage(),
                ]);

                throw $e;
            }

            $this->store()->forget($tokenKey);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Password reset failed', [
                'email' => $data['email'] ?? null,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function findActiveAccountByEmail(string $keyEmail): ?Account
    {
        return Account::query()
            ->whereRaw('LOWER(email) = ?', [$keyEmail])
            ->where('is_active', true)
            ->first();
    }

    private function otpAttemptKey(string $keyEmail): string
    {
        return self::OTP_ATTEMPT_PREFIX.$keyEmail;
    }
}
