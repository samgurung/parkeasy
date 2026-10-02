<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Move to the pooled-card model: an RFID card is a reusable session token,
     * not a permanent owner identity. Each visit (entry) records the card code
     * that was used, so entries are self-contained and the vehicles table is gone.
     */
    public function up(): void
    {
        Schema::table('entries', function (Blueprint $table) {
            $table->string('rfid_id')->nullable()->after('id');
        });

        // Backfill: copy each vehicle's card code onto its existing visits before
        // the vehicles table is dropped.
        foreach (DB::table('vehicles')->get() as $vehicle) {
            DB::table('entries')
                ->where('vehicle_id', $vehicle->id)
                ->whereNull('rfid_id')
                ->update(['rfid_id' => $vehicle->rfid_id]);
        }

        Schema::table('entries', function (Blueprint $table) {
            $table->dropForeign(['vehicle_id']);
            $table->dropColumn('vehicle_id');
        });

        Schema::dropIfExists('vehicles');
    }

    public function down(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone', 10);
            $table->string('rfid_id');
            $table->string('vehicle_type')->nullable();
            $table->timestamps();
        });

        Schema::table('entries', function (Blueprint $table) {
            $table->foreignId('vehicle_id')->nullable()->after('id');
        });

        // Re-create one vehicle per distinct card code and re-link the visits.
        foreach (DB::table('entries')->whereNotNull('rfid_id')->get() as $entry) {
            $vehicleId = DB::table('vehicles')->where('rfid_id', $entry->rfid_id)->value('id');

            if (! $vehicleId) {
                $vehicleId = DB::table('vehicles')->insertGetId([
                    'rfid_id' => $entry->rfid_id,
                    'name' => $entry->driver_name ?? 'Unknown',
                    'phone' => $entry->mobile_number ?? '0000000000',
                    'vehicle_type' => $entry->vehicle_type,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('entries')->where('id', $entry->id)->update(['vehicle_id' => $vehicleId]);
        }
    }
};
