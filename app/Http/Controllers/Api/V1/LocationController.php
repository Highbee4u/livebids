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

        return $this->successResponse(
            CountryResource::collection($countries),
            'Countries retrieved successfully.'
        );
    }

    /**
     * Get states, optionally filtered by country_id.
     */
    public function states(Request $request): JsonResponse
    {
        $countryId = $request->has('country_id') ? (int) $request->query('country_id') : null;
        $states = $this->locationService->getStates($countryId);

        return $this->successResponse(
            StateResource::collection($states),
            'States retrieved successfully.'
        );
    }

    /**
     * Get LGAs / cities by state ID.
     */
    public function cities(int $stateId): JsonResponse
    {
        $lgas = $this->locationService->getCitiesOrLgas($stateId);

        return $this->successResponse(
            LgaResource::collection($lgas),
            'Cities and LGAs retrieved successfully.'
        );
    }
}
