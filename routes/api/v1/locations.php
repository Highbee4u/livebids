<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\LocationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Location Hierarchy Routes (Countries, States, LGAs/Cities)
|--------------------------------------------------------------------------
*/

Route::prefix('locations')->group(function () {
    Route::get('countries', [LocationController::class, 'countries']);
    Route::get('states', [LocationController::class, 'states']);
    Route::get('states/{stateId}/cities', [LocationController::class, 'cities']);
});
