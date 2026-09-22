<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Add a nullable FK first so we can point existing floors at a lot.
        Schema::table('parking_floors', function (Blueprint $table) {
            $table->foreignId('parking_lot_id')->nullable();
            $table->dropUnique('parking_floors_floor_number_unique');
            $table->unique(['parking_lot_id', 'floor_number'], 'parking_floors_lot_floor_unique');
        });

        // 2) Make sure a default lot exists, then backfill any floors that
        //    predate the multi-lot feature into it.
        DB::table('parking_lots')->insertOrIgnore([
            'name'         => 'Main Lot',
            'lot_number'   => 1,
            'address'      => null,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $mainLotId = DB::table('parking_lots')->where('lot_number', 1)->value('id');

        DB::table('parking_floors')->whereNull('parking_lot_id')->update([
            'parking_lot_id' => $mainLotId,
        ]);

        // 3) Now that every floor has a lot, make the FK required.
        Schema::table('parking_floors', function (Blueprint $table) {
            $table->foreignId('parking_lot_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('parking_floors', function (Blueprint $table) {
            $table->dropUnique('parking_floors_lot_floor_unique');
            $table->unique('floor_number', 'parking_floors_floor_number_unique');
            $table->dropColumn('parking_lot_id');
        });
    }
};
