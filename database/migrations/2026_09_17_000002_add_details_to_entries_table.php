<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entries', function (Blueprint $table) {
            $table->string('driver_name')->nullable()->after('vehicle_id');
            $table->string('vehicle_number')->nullable()->after('driver_name');
            $table->string('mobile_number', 10)->nullable()->after('vehicle_number');
        });

        DB::statement("ALTER TABLE entries MODIFY status ENUM('awaiting_details', 'parked', 'exited') NOT NULL DEFAULT 'awaiting_details'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE entries MODIFY status ENUM('inside', 'exited') NOT NULL DEFAULT 'inside'");

        Schema::table('entries', function (Blueprint $table) {
            $table->dropColumn(['driver_name', 'vehicle_number', 'mobile_number']);
        });
    }
};
