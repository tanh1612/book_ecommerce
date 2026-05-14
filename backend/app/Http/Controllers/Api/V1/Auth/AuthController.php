<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\SendOtpRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Http\Resources\AccountResource;
use App\Services\Auth\EmailVerificationService;
use App\Services\Auth\RegisterAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function sendOtp(SendOtpRequest $request, EmailVerificationService $emailVerificationService): JsonResponse
    {
        $emailVerificationService->sendOtp($request->validated('email'));

        return response()->json([
            'message' => 'Mã xác nhận đã được gửi đến email của bạn.',
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

    public function register(RegisterRequest $request, RegisterAccountService $registerAccountService): JsonResponse
    {
        $account = $registerAccountService->register($request->validated());

        Auth::guard('web')->login($account);
        $request->session()->regenerate();

        return (new AccountResource($account))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
