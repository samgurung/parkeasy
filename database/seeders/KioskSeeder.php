<?php

namespace Database\Seeders;

use App\Models\Kiosk;
use App\Models\ParkingLot;
use Illuminate\Database\Seeder;

class KioskSeeder extends Seeder
{
    /**
     * Seed an entry and an exit kiosk for each seeded parking lot. The type is what
     * makes a kiosk admit or release vehicles, so each pair faces opposite ways.
     *
     * The key is the ?kiosk= value the browser is tied to, so it stays stable and
     * human-readable. Renaming a key orphans the kiosk it referred to, so it is only
     * ever changed here.
     *
     * Idempotent: re-running updates by the unique kiosk key instead of duplicating.
     */
    public function run(): void
    {
        $kiosks = [
            ['name' => 'Police Bazaar Entry', 'key' => 'police-bazaar-entry', 'lot_number' => 1, 'type' => Kiosk::TYPE_ENTRY],
            ['name' => 'Police Bazaar Exit', 'key' => 'police-bazaar-exit', 'lot_number' => 1, 'type' => Kiosk::TYPE_EXIT],
            ['name' => 'Tura Bus Stand Entry', 'key' => 'tura-bus-stand-entry', 'lot_number' => 2, 'type' => Kiosk::TYPE_ENTRY],
            ['name' => 'Tura Bus Stand Exit', 'key' => 'tura-bus-stand-exit', 'lot_number' => 2, 'type' => Kiosk::TYPE_EXIT],
            ['name' => 'Sohra Entry', 'key' => 'sohra-entry', 'lot_number' => 3, 'type' => Kiosk::TYPE_ENTRY],
            ['name' => 'Sohra Exit', 'key' => 'sohra-exit', 'lot_number' => 3, 'type' => Kiosk::TYPE_EXIT],
        ];

        foreach ($kiosks as $spec) {
            $lot = ParkingLot::where('lot_number', $spec['lot_number'])->first();

            Kiosk::updateOrCreate(
                ['key' => $spec['key']],
                ['name' => $spec['name'], 'type' => $spec['type'], 'parking_lot_id' => $lot?->id],
            );
        }
    }
}
