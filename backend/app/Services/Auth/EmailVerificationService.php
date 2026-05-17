<?php

namespace App\Services\Auth;

use App\Mail\RegistrationOtpMail;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class EmailVerificationService
{
    private const OTP_PREFIX = 'otp:register:';

    private const COOLDOWN_PREFIX = 'otp:cooldown:';

    private const TOKEN_PREFIX = 'register_token:';

    private const OTP_TTL_SECONDS = 300;

    private const COOLDOWN_TTL_SECONDS = 60;

    private const REGISTER_TOKEN_TTL_SECONDS = 600;

    private function store(): Repository
    {
        return Cache::store(config('registration.cache_store', 'redis'));
    }

    private function normalizedEmailKey(string $email): string
    {
        return strtolower(trim($email));
    }

    private function emailCacheId(string $email): string
    {
        return hash('sha256', $this->normalizedEmailKey($email));
    }

    public function sendOtp(string $email): void
    {
        $cacheId = $this->emailCacheId($email);
        $cooldownKey = self::COOLDOWN_PREFIX.$cacheId;

        try {
            if ($this->store()->has($cooldownKey)) {
                throw ValidationException::withMessages([
                    'email' => ['Gửi mã quá nhanh. Vui lòng thử lại sau 60 giây.'],
                ]);
            }

            $otp = (string) random_int(100_000, 999_999);
            $otpKey = self::OTP_PREFIX.$cacheId;

            $this->store()->put($otpKey, $otp, self::OTP_TTL_SECONDS);
            $this->store()->put($cooldownKey, '1', self::COOLDOWN_TTL_SECONDS);

            try {
                Mail::to($email)->send(new RegistrationOtpMail($otp));
            } catch (Throwable $e) {
                $this->store()->forget($otpKey);
                $this->store()->forget($cooldownKey);
                Log::error('Failed to send registration OTP email', [
                    'email' => $email,
                    'error' => $e->getMessage(),
                ]);

                throw $e;
            }
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Registration OTP send failed', [
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
        $cacheId = $this->emailCacheId($email);
        $otpKey = self::OTP_PREFIX.$cacheId;

        try {
            $stored = $this->store()->get($otpKey);

            if ($stored === null || ! hash_equals((string) $stored, $otp)) {
                throw ValidationException::withMessages([
                    'otp' => ['Mã xác nhận không đúng hoặc đã hết hạn.'],
                ]);
            }

            $this->store()->forget($otpKey);

            $registerToken = Str::random(60);
            $tokenKey = self::TOKEN_PREFIX.$cacheId;
            $this->store()->put($tokenKey, $registerToken, self::REGISTER_TOKEN_TTL_SECONDS);

            return $registerToken;
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Registration OTP verify failed', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function validateRegisterToken(string $email, string $token): bool
    {
        $cacheId = $this->emailCacheId($email);
        $tokenKey = self::TOKEN_PREFIX.$cacheId;

        try {
            $stored = $this->store()->get($tokenKey);

            if ($stored === null) {
                return false;
            }

            return hash_equals((string) $stored, $token);
        } catch (Throwable $e) {
            Log::error('Register token validation read failed', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function deleteRegisterToken(string $email): void
    {
        $cacheId = $this->emailCacheId($email);
        $tokenKey = self::TOKEN_PREFIX.$cacheId;

        try {
            $this->store()->forget($tokenKey);
        } catch (Throwable $e) {
            Log::error('Register token delete failed', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
