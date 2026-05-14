<?php

namespace App\Http\Controllers\Api\V1\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\UpdateProfileRequest;
use App\Http\Resources\AccountResource;
use App\Services\Account\UpdateProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $account = $request->user()->load('profile');

        return (new AccountResource($account))->response();
    }

    public function update(UpdateProfileRequest $request, UpdateProfileService $updateProfileService): JsonResponse
    {
        $account = $updateProfileService->update($request->user(), $request->validated());

        return (new AccountResource($account))->response();
    }
}
