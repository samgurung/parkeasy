<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('vehicle_type')->nullable()->after('rfid_id');
        });

        Schema::table('entries', function (Blueprint $table) {
            $table->string('vehicle_type')->nullable()->after('vehicle_id');
        });
    }

    public function down(): void
    {
        Schema::table('entries', function (Blueprint $table) {
            $table->dropColumn('vehicle_type');
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn('vehicle_type');
        });
    }
};