<?php

use App\Models\ParkingLot;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parking_slots', function (Blueprint $table) {
            $table->string('vehicle_type', 20)->default(ParkingLot::VEHICLE_FOUR_WHEELER)->after('is_occupied');
        });

        // Existing slots predate type designation; treat them as 4-wheeler stalls.
        DB::table('parking_slots')
            ->whereNull('vehicle_type')
            ->update(['vehicle_type' => ParkingLot::VEHICLE_FOUR_WHEELER]);
    }

    public function down(): void
    {
        Schema::table('parking_slots', function (Blueprint $table) {
            $table->dropColumn('vehicle_type');
        });
    }
};
