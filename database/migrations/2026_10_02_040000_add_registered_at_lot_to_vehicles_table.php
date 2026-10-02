<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            // Where the card was first bound, and nothing more. This is provenance, not
            // ownership: it lets a lot admin see the registrations made at their own lot
            // without partitioning the registry. Uniqueness stays site-wide, and a card
            // bound here is still recognised at every other lot.
            $table->foreignId('registered_at_lot_id')
                ->nullable()
                ->after('registered_by_user_id')
                ->constrained('parking_lots')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropForeign(['registered_at_lot_id']);
            $table->dropColumn('registered_at_lot_id');
        });
    }
};
