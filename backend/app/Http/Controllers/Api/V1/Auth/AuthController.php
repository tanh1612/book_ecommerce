<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\SendOtpRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Http\Resources\AccountResource;
use App\Services\Auth\EmailVerificationService;
use App\Services\Auth\LoginAccountService;
use App\Services\Auth\RegisterAccountService;
use App\Services\Cart\GuestCartTokenService;
use App\Services\Cart\MergeGuestCartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AuthController extends Controller
{
    public function sendOtp(SendOtpRequest $request, EmailVerificationService $emailVerificationService): JsonResponse
    {
        $emailVerificationService->sendOtp($request->validated('email'));

        return response()->json([
            'message' => 'Mã xác nhận đã được gửi đến email '.$request->validated('email').'.',
        ], Response::HTTP_OK);
    }

    public function verifyOtp(VerifyOtpRequest $request, EmailVerificationService $emailVerificationService): JsonResponse
    {
        $registerToken = $emailVerificationService->verifyOtp(
            $request->validated('email'),
            $request->validated('otp'),
        );

        return response()->json([
            'register_token' => $registerToken,
        ], Response::HTTP_OK);
    }

    public function register(
        RegisterRequest $request,
        RegisterAccountService $registerAccountService,
        MergeGuestCartService $mergeGuestCartService,
        GuestCartTokenService $guestCartTokenService,
    ): JsonResponse {
        $guestToken = $guestCartTokenService->getRawTokenFromRequest();
        $account = $registerAccountService->register($request->validated());
        $warnings = $this->mergeGuestCartWithWarnings(
            fn () => $mergeGuestCartService->assignGuestCartToNewAccount($guestToken, $account),
            $account->id,
            'register',
        );

        return (new AccountResource($account))
            ->additional($this->authResponseMeta($warnings))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function login(
        LoginRequest $request,
        LoginAccountService $loginAccountService,
        MergeGuestCartService $mergeGuestCartService,
        GuestCartTokenService $guestCartTokenService,
    ): JsonResponse {
        $preLoginGuestToken = $guestCartTokenService->getRawTokenFromRequest();
        $account = $loginAccountService->login($request->validated(), $request->ip());
        $warnings = $this->mergeGuestCartWithWarnings(
            fn () => $mergeGuestCartService->mergeGuestCartAfterLogin($preLoginGuestToken, $account),
            $account->id,
            'login',
        );

        return (new AccountResource($account))
            ->additional($this->authResponseMeta($warnings))
            ->response();
    }

    public function logout(Request $request): Response
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    /**
     * @param  callable(): void  $merge
     * @return list<string>
     */
    private function mergeGuestCartWithWarnings(callable $merge, int $accountId, string $flow): array
    {
        try {
            $merge();

            return [];
        } catch (Throwable $e) {
            Log::error('Guest cart merge failed after auth', [
                'account_id' => $accountId,
                'flow' => $flow,
                'error' => $e->getMessage(),
            ]);

            return [
                'Không thể gộp giỏ hàng khách. Vui lòng kiểm tra lại giỏ hàng.',
            ];
        }
    }

    /**
     * @param  list<string>  $warnings
     * @return array<string, mixed>
     */
    private function authResponseMeta(array $warnings): array
    {
        if ($warnings === []) {
            return [];
        }

        return ['warnings' => $warnings];
    }
}
