<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parking_lots', function (Blueprint $table) {
            $table->id();
            $table->string('name');                 // driver-facing name, e.g. "Downtown Plaza"
            $table->unsignedInteger('lot_number');  // auto-assigned 1, 2, 3, … sent by ESP32
            $table->string('address')->nullable();  // street address to help drivers find the lot
            $table->timestamps();

            $table->unique('lot_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parking_lots');
    }
};
