<?php

namespace Database\Seeders;

use App\Models\Vehicle;
use Illuminate\Database\Seeder;

class VehicleSeeder extends Seeder
{
    /**
     * Seed a few registered vehicles so the kiosks have cards that can actually
     * be scanned end to end.
     *
     * Plates use the Meghalaya 'ML' series (Shillong ML-01/02, Tura ML-05,
     * Jowai ML-11) and the names are drawn from Khasi, Garo and Jaintia
     * communities, matching the lots these cards would park at.
     *
     * Idempotent: re-running updates by the unique card code instead of duplicating.
     */
    public function run(): void
    {
        $vehicles = [
            ['rfid_id' => 'RFID-0001', 'vehicle_number' => 'ML01AB1234', 'vehicle_type' => 'four_wheeler', 'driver_name' => 'Dawkmarla Marak', 'mobile_number' => '8796412301'],
            ['rfid_id' => 'RFID-0002', 'vehicle_number' => 'ML01CD5678', 'vehicle_type' => 'four_wheeler', 'driver_name' => 'Balram Syiem', 'mobile_number' => '8796412302'],
            ['rfid_id' => 'RFID-0003', 'vehicle_number' => 'ML05EF9012', 'vehicle_type' => 'two_wheeler', 'driver_name' => 'Rangmanik M Sangma', 'mobile_number' => '8796412303'],
            ['rfid_id' => 'RFID-0004', 'vehicle_number' => 'ML11GH3456', 'vehicle_type' => 'two_wheeler', 'driver_name' => 'Dongru Lytong', 'mobile_number' => '8796412304'],
        ];

        foreach ($vehicles as $spec) {
            Vehicle::updateOrCreate(
                ['rfid_id' => $spec['rfid_id']],
                $spec,
            );
        }
    }
}
