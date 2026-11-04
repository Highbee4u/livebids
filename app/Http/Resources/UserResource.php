<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'phone' => $this->phone,
            'name' => $this->name,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'legal_name' => $this->legal_name,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'email' => $this->email,
            'avatar_url' => $this->avatar_url,
            'is_phone_verified' => $this->isPhoneVerified(),
            'is_profile_completed' => $this->isProfileCompleted(),
            'is_onboarded' => $this->isOnboarded(),
            'kyc_status' => $this->kyc_status?->value ?? 'pending',
            'is_kyc_verified' => $this->isKycVerified(),
            'rating' => (float) $this->rating,
            'review_count' => $this->review_count,
            'strikes_count' => $this->strikes_count,
            'pickup_area' => $this->pickup_area,
            'primary_address' => new UserAddressResource($this->whenLoaded('primaryAddress')),
            'roles' => $this->getRoleNames(),
            'notifications_enabled' => [
                'email' => $this->email_notifications_enabled,
                'sms' => $this->sms_notifications_enabled,
                'push' => $this->push_notifications_enabled,
            ],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
