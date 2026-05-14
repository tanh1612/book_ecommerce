<?php

namespace App\Http\Controllers\Api\V1\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\ChangePasswordRequest;
use App\Services\Account\ChangePasswordService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class PasswordController extends Controller
{
    public function update(ChangePasswordRequest $request, ChangePasswordService $changePasswordService): JsonResponse
    {
        $changePasswordService->change($request->user(), $request->validated());

        return response()->json([
            'message' => 'Mật khẩu đã được đổi thành công.',
        ], Response::HTTP_OK);
    }
}
