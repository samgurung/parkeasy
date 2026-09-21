<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parking_floors', function (Blueprint $table) {
            $table->id();
            $table->string('name');                   // e.g. "Ground Floor", "Floor 1"
            $table->unsignedInteger('floor_number');  // numeric id sent by ESP32 (0 = ground)
            $table->unsignedInteger('slot_count')->default(0); // total configured slots
            $table->timestamps();

            $table->unique('floor_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parking_floors');
    }
};
