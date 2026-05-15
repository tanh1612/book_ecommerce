<?php

namespace App\Http\Controllers\Api\V1\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\StoreAddressRequest;
use App\Http\Requests\Account\UpdateAddressRequest;
use App\Http\Resources\AddressResource;
use App\Services\Account\CreateAddressService;
use App\Services\Account\DeleteAddressService;
use App\Services\Account\UpdateAddressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class AddressController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $addresses = $request->user()
            ->addresses()
            ->orderByDesc('is_default')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return AddressResource::collection($addresses);
    }

    public function store(StoreAddressRequest $request, CreateAddressService $createAddressService): JsonResponse
    {
        $address = $createAddressService->create($request->user(), $request->validated());

        return (new AddressResource($address))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateAddressRequest $request, string $address, UpdateAddressService $updateAddressService): JsonResponse
    {
        $model = $updateAddressService->update($request->user(), (int) $address, $request->validated());

        return (new AddressResource($model))->response()->setStatusCode(Response::HTTP_OK);
    }

    public function destroy(Request $request, string $address, DeleteAddressService $deleteAddressService): Response
    {
        $deleteAddressService->delete($request->user(), (int) $address);

        return response()->noContent();
    }
}
