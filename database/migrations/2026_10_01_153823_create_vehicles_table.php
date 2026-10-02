<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Each RFID card is permanently tied to one vehicle (like FASTag).
     * The vehicle stores all the details needed for automated entry/exit.
     */
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('rfid_id')->unique();
            $table->string('vehicle_number');
            $table->string('vehicle_type');
            $table->string('driver_name');
            $table->string('mobile_number', 10);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
