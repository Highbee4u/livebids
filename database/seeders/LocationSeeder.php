<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Identity\Models\Country;
use App\Domain\Identity\Models\Lga;
use App\Domain\Identity\Models\State;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class LocationSeeder extends Seeder
{
    /**
     * Run the database seeds for Nigeria, its 36 States + FCT, and 774 LGAs.
     */
    public function run(): void
    {
        // 1. Seed Nigeria
        $country = Country::firstOrCreate(
            ['iso2' => 'NG'],
            [
                'name' => 'Nigeria',
                'iso3' => 'NGA',
                'phone_code' => '234',
                'currency' => 'NGN',
                'currency_symbol' => '₦',
                'active' => true,
            ]
        );

        // 2. Read states and LGAs JSON
        $dataPath = database_path('data/nigeria_states_lgas.json');
        if (! File::exists($dataPath)) {
            return;
        }

        $statesLgas = json_decode(File::get($dataPath), true);
        if (! is_array($statesLgas)) {
            return;
        }

        foreach ($statesLgas as $stateName => $lgas) {
            $state = State::firstOrCreate(
                [
                    'country_id' => $country->id,
                    'name' => (string) $stateName,
                ],
                [
                    'state_code' => strtoupper(substr((string) $stateName, 0, 3)),
                ]
            );

            if (is_array($lgas)) {
                foreach ($lgas as $lgaName) {
                    Lga::firstOrCreate([
                        'state_id' => $state->id,
                        'name' => (string) $lgaName,
                    ]);
                }
            }
        }
    }
}
