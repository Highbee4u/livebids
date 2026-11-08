<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Identity\Enums\KycStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        $firstName = fake()->firstName();
        $lastName = fake()->lastName();

        return [
            'identifier' => fake()->unique()->uuid(),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'name' => "{$firstName} {$lastName}",
            'date_of_birth' => now()->subYears(25)->toDateString(),
            'phone' => '+234'.fake()->unique()->numerify('80########'),
            'email' => fake()->unique()->safeEmail(),
            'phone_verified_at' => now(),
            'profile_completed_at' => now(),
            'email_verified_at' => now(),
            'kyc_status' => KycStatus::Pending,
            'rating' => 5.00,
            'review_count' => 0,
            'strikes_count' => 0,
            'is_suspended' => false,
            'is_banned' => false,
            'remember_token' => Str::random(10),
        ];
    }

    public function unonboarded(): static
    {
        return $this->state(fn () => [
            'first_name' => null,
            'last_name' => null,
            'name' => 'LiveBids User',
            'date_of_birth' => null,
            'phone_verified_at' => null,
            'profile_completed_at' => null,
        ]);
    }

    public function phoneVerifiedOnly(): static
    {
        return $this->state(fn () => [
            'first_name' => null,
            'last_name' => null,
            'name' => 'LiveBids User',
            'date_of_birth' => null,
            'phone_verified_at' => now(),
            'profile_completed_at' => null,
        ]);
    }

    public function verified(): static
    {
        return $this->state(fn () => [
            'kyc_status' => KycStatus::Verified,
            'legal_name' => fake()->name(),
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => [
            'is_suspended' => true,
        ]);
    }
}
