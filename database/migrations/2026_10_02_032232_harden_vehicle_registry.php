<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The vehicle registry is site-wide, not per-lot: a card bound at one gate has to be
     * recognised at every other gate, so the database - not just the UI - has to refuse a
     * second row claiming the same card or registration. `rfid_id` is already unique; this
     * closes the remaining gap on the registration number.
     *
     * `registered_by_user_id` is audit only. It records which admin bound the card, and
     * deliberately carries no lot: who happened to be on shift when a car first arrived
     * must not become part of the vehicle's identity.
     */
    public function up(): void
    {
        $duplicates = DB::table('vehicles')
            ->select('vehicle_number', DB::raw('COUNT(*) AS total'))
            ->groupBy('vehicle_number')
            ->having('total', '>', 1)
            ->pluck('vehicle_number');

        if ($duplicates->isNotEmpty()) {
            // Refuse to guess. Silently keeping one of two conflicting rows could park the
            // wrong vehicle, so a human has to resolve these before the index can be added.
            throw new RuntimeException(
                'Cannot enforce a unique vehicle_number: duplicates exist for '
                .implode(', ', $duplicates->all())
            );
        }

        Schema::table('vehicles', function (Blueprint $table) {
            $table->unique('vehicle_number');
            $table->foreignId('registered_by_user_id')
                ->nullable()
                ->after('mobile_number')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropForeign(['registered_by_user_id']);
            $table->dropColumn('registered_by_user_id');
            $table->dropUnique(['vehicle_number']);
        });
    }
};
