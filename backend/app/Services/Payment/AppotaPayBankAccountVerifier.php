<?php

namespace App\Services\Payment;

use App\Contracts\Payment\BankAccountVerifier;
use App\DataTransferObjects\Payment\VerifiedBankAccount;
use App\Exceptions\Payment\BankAccountVerificationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class AppotaPayBankAccountVerifier implements BankAccountVerifier
{
    public function __construct(
        private readonly AppotaPayJwtTokenFactory $jwtTokenFactory,
        private readonly AppotaPayBankCodeMapper $bankCodeMapper,
    ) {}

    public function verify(int $bankBin, string $accountNumber, string $bankCode, string $bankName): VerifiedBankAccount
    {
        $partnerCode = (string) config('refund.verification.appotapay.partner_code', '');
        $apiKey = (string) config('refund.verification.appotapay.api_key', '');
        $secretKey = (string) config('refund.verification.appotapay.secret_key', '');
        $baseUrl = rtrim((string) config('refund.verification.appotapay.base_url', ''), '/');

        if ($partnerCode === '' || $apiKey === '' || $secretKey === '' || $baseUrl === '') {
            throw new BankAccountVerificationException(
                'Dịch vụ xác minh tài khoản chưa được cấu hình. Vui lòng thử lại sau.',
                'misconfigured',
            );
        }

        $appotaPayBankCode = $this->bankCodeMapper->resolve($bankCode);

        if ($appotaPayBankCode === null) {
            throw new BankAccountVerificationException(
                'Ngân hàng không hỗ trợ tra cứu số tài khoản.',
                'bank_unmapped',
            );
        }

        $accountType = 'account';
        $partnerRefId = 'refund-'.Str::uuid()->toString();
        $signaturePayload = [
            'accountNo' => $accountNumber,
            'accountType' => $accountType,
            'bankCode' => $appotaPayBankCode,
            'partnerRefId' => $partnerRefId,
        ];

        $requestBody = [
            ...$signaturePayload,
            'signature' => $this->sign($signaturePayload, $secretKey),
        ];

        $endpoint = $baseUrl.'/api/v1/service/transfer/bank/account/info';

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'X-APPOTAPAY-AUTH' => 'Bearer '.$this->jwtTokenFactory->create(),
                    'Content-Type' => 'application/json',
                    'Language' => 'vi',
                ])
                ->post($endpoint, $requestBody);
        } catch (ConnectionException $e) {
            Log::error('AppotaPay account info connection failed', [
                'bank_code' => $bankCode,
                'error' => $e->getMessage(),
            ]);

            throw new BankAccountVerificationException(
                'Không thể kết nối dịch vụ xác minh tài khoản. Vui lòng thử lại sau.',
                previous: $e,
            );
        } catch (Throwable $e) {
            Log::error('AppotaPay account info request failed', [
                'bank_code' => $bankCode,
                'error' => $e->getMessage(),
            ]);

            throw new BankAccountVerificationException(
                'Không thể xác minh số tài khoản. Vui lòng thử lại sau.',
                previous: $e,
            );
        }

        if (! $response->successful()) {
            /** @var array<string, mixed>|null $errorBody */
            $errorBody = $response->json();

            Log::warning('AppotaPay account info HTTP error', [
                'bank_code' => $bankCode,
                'status' => $response->status(),
                'error_code' => $errorBody['errorCode'] ?? null,
                'message' => $errorBody['message'] ?? null,
            ]);

            throw new BankAccountVerificationException(
                'Không thể xác minh số tài khoản. Vui lòng kiểm tra lại thông tin.',
                (string) $response->status(),
            );
        }

        /** @var array<string, mixed>|null $body */
        $body = $response->json();
        $errorCode = (int) ($body['errorCode'] ?? -1);
        $accountInfo = $body['accountInfo'] ?? [];
        $accountName = is_array($accountInfo)
            ? trim((string) ($accountInfo['accountName'] ?? ''))
            : '';

        if ($errorCode !== 0 || $accountName === '') {
            throw new BankAccountVerificationException(
                'Số tài khoản không tồn tại hoặc ngân hàng không hỗ trợ tra cứu.',
                (string) $errorCode,
            );
        }

        return new VerifiedBankAccount(
            bankCode: $bankCode,
            bankName: $bankName,
            bankBin: $bankBin,
            accountNumber: $accountNumber,
            accountHolder: $accountName,
            provider: 'appotapay',
            providerCode: (string) $errorCode,
        );
    }

    /**
     * @param  array<string, string>  $params
     */
    private function sign(array $params, string $secretKey): string
    {
        ksort($params);

        $pairs = [];

        foreach ($params as $key => $value) {
            $pairs[] = "{$key}={$value}";
        }

        return hash_hmac('sha256', implode('&', $pairs), $secretKey);
    }
}
