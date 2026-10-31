<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    /**
     * Get the authenticated user's profile.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => new UserResource($request->user()),
        ]);
    }

    /**
     * Update profile details and notification settings.
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update($request->validated());

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Get public profile and trust metrics of any seller/user.
     */
    public function publicProfile(int $id): JsonResponse
    {
        $target = User::findOrFail($id);

        return response()->json([
            'user' => [
                'id' => $target->id,
                'name' => $target->name,
                'avatar_url' => $target->avatar_url,
                'rating' => (float) $target->rating,
                'review_count' => $target->review_count,
                'strikes_count' => $target->strikes_count,
                'pickup_area' => $target->pickup_area,
                'is_verified' => $target->isKycVerified(),
                'member_since' => $target->created_at?->toFormattedDateString(),
            ],
        ]);
    }
}
