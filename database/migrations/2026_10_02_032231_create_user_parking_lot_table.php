<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A lot admin administers the lots they are attached to here. This is a plain pivot
     * rather than Spatie's teams feature: teams would require a team_id on every
     * lot-scoped model, and the lot itself is the team boundary, so an explicit
     * many-to-many is both simpler and the honest representation of the rule.
     */
    public function up(): void
    {
        Schema::create('user_parking_lot', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parking_lot_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['user_id', 'parking_lot_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_parking_lot');
    }
};
