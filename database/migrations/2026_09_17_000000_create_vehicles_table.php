<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();                   // Primary key
            $table->string('name');         // Vehicle owner name
            $table->string('phone', 10);    // 10-digit phone number
            $table->string('rfid_id');      // Unique RFID identifier
            $table->timestamps();           // created_at & updated_at
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