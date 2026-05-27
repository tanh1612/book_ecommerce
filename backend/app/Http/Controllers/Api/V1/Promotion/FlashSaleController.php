<?php

namespace App\Http\Controllers\Api\V1\Promotion;

use App\Http\Controllers\Controller;
use App\Http\Resources\FlashSaleResource;
use App\Services\Promotion\FlashSaleCatalogService;
use Illuminate\Http\JsonResponse;

class FlashSaleController extends Controller
{
    public function active(FlashSaleCatalogService $flashSaleCatalogService): JsonResponse|FlashSaleResource
    {
        $payload = $flashSaleCatalogService->activeCampaignWithItems();

        if ($payload === null) {
            return response()->json(['data' => null]);
        }

        return (new FlashSaleResource($payload))->response();
    }
}
