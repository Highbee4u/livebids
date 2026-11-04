<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Services\LocationService;
use App\Http\Controllers\Controller;
use App\Http\Resources\CountryResource;
use App\Http\Resources\LgaResource;
use App\Http\Resources\StateResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    public function __construct(
        protected LocationService $locationService
    ) {}

    /**
     * Get all supported countries.
     */
    public function countries(): JsonResponse
    {
        $countries = $this->locationService->getCountries();

        return response()->json([
            'status' => 'success',
            'data' => CountryResource::collection($countries),
        ]);
    }

    /**
     * Get states, optionally filtered by country_id.
     */
    public function states(Request $request): JsonResponse
    {
        $countryId = $request->has('country_id') ? (int) $request->query('country_id') : null;
        $states = $this->locationService->getStates($countryId);

        return response()->json([
            'status' => 'success',
            'data' => StateResource::collection($states),
        ]);
    }

    /**
     * Get LGAs / cities by state ID.
     */
    public function cities(int $stateId): JsonResponse
    {
        $lgas = $this->locationService->getCitiesOrLgas($stateId);

        return response()->json([
            'status' => 'success',
            'data' => LgaResource::collection($lgas),
        ]);
    }
}
