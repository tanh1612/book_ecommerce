<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\SendPasswordResetOtpRequest;
use App\Http\Requests\Auth\VerifyPasswordResetOtpRequest;
use App\Services\Auth\PasswordResetService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ForgotPasswordController extends Controller
{
    public function sendOtp(SendPasswordResetOtpRequest $request, PasswordResetService $passwordResetService): JsonResponse
    {
        $passwordResetService->sendOtp($request->validated('email'));

        return response()->json([
            'message' => 'Nếu email tồn tại trong hệ thống, mã xác nhận đặt lại mật khẩu đã được gửi.',
        ], Response::HTTP_OK);
    }

    public function verifyOtp(VerifyPasswordResetOtpRequest $request, PasswordResetService $passwordResetService): JsonResponse
    {
        $resetToken = $passwordResetService->verifyOtp(
            $request->validated('email'),
            $request->validated('otp'),
        );

        return response()->json([
            'reset_token' => $resetToken,
        ], Response::HTTP_OK);
    }

    public function reset(ResetPasswordRequest $request, PasswordResetService $passwordResetService): JsonResponse
    {
        $passwordResetService->resetPassword($request->validated());

        return response()->json([
            'message' => 'Mật khẩu đã được đặt lại thành công.',
        ], Response::HTTP_OK);
    }
}
