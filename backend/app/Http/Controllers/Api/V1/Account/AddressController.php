<?php

namespace App\Http\Controllers\Api\V1\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\StoreAddressRequest;
use App\Http\Resources\AddressResource;
use App\Services\Account\CreateAddressService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class AddressController extends Controller
{
    public function store(StoreAddressRequest $request, CreateAddressService $createAddressService): JsonResponse
    {
        $address = $createAddressService->create($request->user(), $request->validated());

        return (new AddressResource($address))->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
