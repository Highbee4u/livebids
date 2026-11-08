<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Identity\Models\Country;
use App\Domain\Identity\Models\State;
use Database\Seeders\LocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LocationSeeder::class);
    }

    public function test_can_list_supported_countries(): void
    {
        $response = $this->getJson('/api/v1/locations/countries');

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonFragment([
                'name' => 'Nigeria',
                'iso2' => 'NG',
                'currency' => 'NGN',
            ]);
    }

    public function test_can_list_states_for_nigeria(): void
    {
        $nigeria = Country::where('iso2', 'NG')->first();

        $response = $this->getJson("/api/v1/locations/states?country_id={$nigeria->id}");

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonFragment(['name' => 'Lagos'])
            ->assertJsonFragment(['name' => 'Abuja Federal Capital Territory']);
    }

    public function test_can_list_cities_or_lgas_for_state(): void
    {
        $lagos = State::where('name', 'Lagos')->first();

        $response = $this->getJson("/api/v1/locations/states/{$lagos->id}/cities");

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonFragment(['name' => 'Ikeja'])
            ->assertJsonFragment(['name' => 'Lagos Island']);
    }
}
