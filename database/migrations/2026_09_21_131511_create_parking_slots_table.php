<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parking_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parking_floor_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('slot_number');          // slot number within the floor (1-based)
            $table->string('label')->nullable();             // optional custom label e.g. "A1"
            $table->boolean('is_occupied')->default(false);  // true = car present
            $table->timestamp('last_updated_at')->nullable(); // when ESP32 last reported status
            $table->timestamps();

            $table->unique(['parking_floor_id', 'slot_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parking_slots');
    }
};
