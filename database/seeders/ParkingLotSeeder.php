<?php

namespace Database\Seeders;

use App\Models\ParkingFloor;
use App\Models\ParkingLot;
use Illuminate\Database\Seeder;

class ParkingLotSeeder extends Seeder
{
    /**
     * Seed a handful of parking lots, each with a few floors and their slots.
     *
     * Idempotent: re-running will update existing lots/floors instead of
     * duplicating them (lot_number is unique).
     */
    public function run(): void
    {
        $lots = [
            [
                'lot_number' => 1,
                'name' => 'Downtown Plaza',
                'address' => '12 MG Road, Bengaluru',
                'rate_two_wheeler' => 10,
                'rate_four_wheeler' => 30,
                'floors' => [
                    ['name' => 'Ground Floor', 'slot_count' => 12],
                    ['name' => 'First Floor', 'slot_count' => 10],
                ],
            ],
            [
                'lot_number' => 2,
                'name' => 'Railway Junction',
                'address' => '3 Station Road, Bengaluru',
                'rate_two_wheeler' => 8,
                'rate_four_wheeler' => 25,
                'floors' => [
                    ['name' => 'Ground Floor', 'slot_count' => 8],
                ],
            ],
            [
                'lot_number' => 3,
                'name' => 'City Mall',
                'address' => '88 Outer Ring Road, Bengaluru',
                'rate_two_wheeler' => 15,
                'rate_four_wheeler' => 40,
                'floors' => [
                    ['name' => 'Ground Floor', 'slot_count' => 15],
                    ['name' => 'First Floor', 'slot_count' => 12],
                    ['name' => 'Second Floor', 'slot_count' => 8],
                ],
            ],
        ];

        foreach ($lots as $spec) {
            $floors = $spec['floors'];
            unset($spec['floors']);

            $lot = ParkingLot::updateOrCreate(['lot_number' => $spec['lot_number']], $spec);

            foreach ($floors as $index => $floorSpec) {
                $floor = ParkingFloor::updateOrCreate(
                    [
                        'parking_lot_id' => $lot->id,
                        'floor_number' => $index + 1,
                    ],
                    [
                        'name' => $floorSpec['name'],
                        'slot_count' => $floorSpec['slot_count'],
                    ]
                );

                $floor->syncSlots();
            }
        }
    }
}