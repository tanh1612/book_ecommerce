<?php

namespace App\Http\Controllers\Api\V1\Location;

use App\Http\Controllers\Controller;
use App\Http\Resources\LocationListResource;
use App\Services\Location\LocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class LocationController extends Controller
{
    public function provinces(Request $request, LocationService $locationService): JsonResponse
    {
        $validated = $request->validate([
            'keyword' => ['sometimes', 'nullable', 'string', 'max:255'],
            'limit' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ]);

        try {
            $payload = $locationService->getNewProvinces(
                $validated['keyword'] ?? null,
                isset($validated['limit']) ? (int) $validated['limit'] : null,
                isset($validated['page']) ? (int) $validated['page'] : null,
            );
        } catch (Throwable) {
            return response()->json([
                'message' => 'Địa giới hành chính tạm thời không khả dụng. Vui lòng thử lại sau.',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return (new LocationListResource([
            'data' => $payload['data'] ?? [],
            'metadata' => $payload['metadata'] ?? null,
        ]))->response()->setStatusCode(Response::HTTP_OK);
    }

    public function wards(Request $request, string $provinceCode, LocationService $locationService): JsonResponse
    {
        $validated = $request->validate([
            'keyword' => ['sometimes', 'nullable', 'string', 'max:255'],
            'limit' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ]);

        $provinceCode = trim($provinceCode);
        if ($provinceCode === '') {
            return response()->json([
                'message' => 'Invalid province code.',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $payload = $locationService->getNewProvinceWards(
                $provinceCode,
                $validated['keyword'] ?? null,
                isset($validated['limit']) ? (int) $validated['limit'] : null,
                isset($validated['page']) ? (int) $validated['page'] : null,
            );
        } catch (Throwable) {
            return response()->json([
                'message' => 'Địa giới hành chính tạm thời không khả dụng. Vui lòng thử lại sau.',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return (new LocationListResource([
            'data' => $payload['data'] ?? [],
            'metadata' => $payload['metadata'] ?? null,
        ]))->response()->setStatusCode(Response::HTTP_OK);
    }
}
