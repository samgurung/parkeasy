<?php

namespace Database\Seeders;

use App\Models\Kiosk;
use App\Models\ParkingLot;
use Illuminate\Database\Seeder;

class KioskSeeder extends Seeder
{
    /**
     * Seed an entry and exit kiosk for each seeded parking lot.
     *
     * Idempotent: re-running updates by the unique kiosk key instead of duplicating.
     */
    public function run(): void
    {
        $kiosks = [
            ['name' => 'Downtown Entry', 'key' => 'downtown-entry', 'lot_number' => 1],
            ['name' => 'Downtown Exit', 'key' => 'downtown-exit', 'lot_number' => 1],
            ['name' => 'Railway Entry', 'key' => 'railway-entry', 'lot_number' => 2],
            ['name' => 'Railway Exit', 'key' => 'railway-exit', 'lot_number' => 2],
            ['name' => 'City Mall Entry', 'key' => 'city-mall-entry', 'lot_number' => 3],
            ['name' => 'City Mall Exit', 'key' => 'city-mall-exit', 'lot_number' => 3],
        ];

        foreach ($kiosks as $spec) {
            $lot = ParkingLot::where('lot_number', $spec['lot_number'])->first();

            Kiosk::updateOrCreate(
                ['key' => $spec['key']],
                ['name' => $spec['name'], 'parking_lot_id' => $lot?->id],
            );
        }
    }
}