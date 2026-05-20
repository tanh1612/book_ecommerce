<?php

namespace App\Services\Payment;

class AppotaPayJwtTokenFactory
{
    public function create(): string
    {
        $partnerCode = (string) config('refund.verification.appotapay.partner_code', '');
        $apiKey = (string) config('refund.verification.appotapay.api_key', '');
        $secretKey = (string) config('refund.verification.appotapay.secret_key', '');

        $issuedAt = time();

        $header = $this->base64UrlEncode(json_encode([
            'typ' => 'JWT',
            'alg' => 'HS256',
            'cty' => 'appotapay-api;v=1',
        ], JSON_THROW_ON_ERROR));

        $payload = $this->base64UrlEncode(json_encode([
            'iss' => $partnerCode,
            'jti' => $apiKey.'-'.$issuedAt,
            'api_key' => $apiKey,
            'exp' => $issuedAt + max(60, (int) config('refund.verification.appotapay.jwt_ttl_seconds', 300)),
        ], JSON_THROW_ON_ERROR));

        $signature = $this->base64UrlEncode(
            hash_hmac('sha256', "{$header}.{$payload}", $secretKey, true),
        );

        return "{$header}.{$payload}.{$signature}";
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
