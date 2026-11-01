<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\FeedController;
use App\Http\Controllers\Api\V1\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Discovery, Feeds & Public Profile Routes
|--------------------------------------------------------------------------
*/

Route::get('users/{id}/public', [ProfileController::class, 'publicProfile']);

Route::prefix('feed')->group(function () {
    Route::get('upcoming', [FeedController::class, 'upcoming']);
    Route::get('live', [FeedController::class, 'live']);
});

Route::get('categories', [FeedController::class, 'categories']);
