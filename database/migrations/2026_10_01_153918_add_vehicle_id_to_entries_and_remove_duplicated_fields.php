<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Move from pooled cards to vehicle-bound cards: each RFID becomes permanently
     * tied to one vehicle, so a visit no longer stores driver details of its own.
     *
     * Legacy visits kept those details per row, so they are lifted into a vehicle
     * before the columns go away, otherwise the history would be lost.
     */
    public function up(): void
    {
        Schema::table('entries', function (Blueprint $table) {
            $table->foreignId('vehicle_id')->nullable()->after('rfid_id')->constrained();
        });

        $this->backfillVehicles();

        Schema::table('entries', function (Blueprint $table) {
            $table->dropColumn(['driver_name', 'vehicle_number', 'mobile_number', 'vehicle_type']);
        });
    }

    /**
     * Create one vehicle per distinct card code, taking the details from that card's
     * most recent visit. Cards were pooled, so a single card may have driven several
     * vehicles over time; the latest visit is the best available identity for it.
     */
    protected function backfillVehicles(): void
    {
        $now = now();

        DB::table('entries')
            ->whereNotNull('rfid_id')
            ->whereNotNull('vehicle_number')
            ->select('rfid_id')
            ->distinct()
            ->orderBy('rfid_id')
            ->pluck('rfid_id')
            ->each(function (string $rfid) use ($now) {
                $latest = DB::table('entries')
                    ->where('rfid_id', $rfid)
                    ->whereNotNull('vehicle_number')
                    ->orderByDesc('id')
                    ->first();

                if (! $latest) {
                    return;
                }

                $vehicleId = DB::table('vehicles')->insertGetId([
                    'rfid_id' => $rfid,
                    'vehicle_number' => $latest->vehicle_number,
                    'vehicle_type' => $latest->vehicle_type ?: 'four_wheeler',
                    'driver_name' => $latest->driver_name ?: 'Unknown',
                    'mobile_number' => $latest->mobile_number ?: '0000000000',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('entries')->where('rfid_id', $rfid)->update(['vehicle_id' => $vehicleId]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entries', function (Blueprint $table) {
            $table->string('driver_name')->nullable()->after('vehicle_id');
            $table->string('vehicle_number')->nullable()->after('driver_name');
            $table->string('mobile_number', 10)->nullable()->after('vehicle_number');
            $table->string('vehicle_type')->nullable()->after('mobile_number');
        });

        // Re-state the details on each visit from the vehicle it now points at.
        DB::table('entries')
            ->join('vehicles', 'entries.vehicle_id', '=', 'vehicles.id')
            ->select('entries.id', 'vehicles.vehicle_number', 'vehicles.vehicle_type', 'vehicles.driver_name', 'vehicles.mobile_number')
            ->orderBy('entries.id')
            ->each(function ($row) {
                DB::table('entries')->where('id', $row->id)->update([
                    'driver_name' => $row->driver_name,
                    'vehicle_number' => $row->vehicle_number,
                    'mobile_number' => $row->mobile_number,
                    'vehicle_type' => $row->vehicle_type,
                ]);
            });

        Schema::table('entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vehicle_id');
        });
    }
};
