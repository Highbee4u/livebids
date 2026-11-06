<?php

declare(strict_types=1);

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Models\Country;
use App\Domain\Identity\Models\Lga;
use App\Domain\Identity\Models\State;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class LocationService
{
    private const CACHE_TTL = 86400; // 24 hours

    /**
     * Get all active countries (cached).
     *
     * @return Collection<int, Country>
     */
    public function getCountries(): Collection
    {
        $data = Cache::remember('locations:countries:active', self::CACHE_TTL, function () {
            return Country::where('active', true)->orderBy('name')->get()->toArray();
        });

        return Country::hydrate($data);
    }

    /**
     * Get states, optionally filtered by country_id (cached).
     *
     * @return Collection<int, State>
     */
    public function getStates(?int $countryId = null): Collection
    {
        $cacheKey = $countryId ? "locations:states:country:{$countryId}" : 'locations:states:all';

        $data = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($countryId) {
            $query = State::orderBy('name');
            if ($countryId) {
                $query->where('country_id', $countryId);
            }

            return $query->get()->toArray();
        });

        return State::hydrate($data);
    }

    /**
     * Get LGAs / cities belonging to a state (cached).
     *
     * @return Collection<int, Lga>
     */
    public function getCitiesOrLgas(int $stateId): Collection
    {
        $data = Cache::remember("locations:lgas:state:{$stateId}", self::CACHE_TTL, function () use ($stateId) {
            return Lga::where('state_id', $stateId)->orderBy('name')->get()->toArray();
        });

        return Lga::hydrate($data);
    }

    /**
     * Validate whether an LGA belongs to a given State.
     */
    public function lgaBelongsToState(int $lgaId, int $stateId): bool
    {
        return Lga::where('id', $lgaId)->where('state_id', $stateId)->exists();
    }
}
