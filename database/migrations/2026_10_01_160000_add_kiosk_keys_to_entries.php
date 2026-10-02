<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entries', function (Blueprint $table) {
            // A visit touches two different kiosks: the one that admitted the vehicle and
            // the one that released it. Both are recorded so each kiosk can show only the
            // scans it actually handled.
            $table->string('entry_kiosk_key')->nullable()->after('parking_lot_id');
            $table->string('exit_kiosk_key')->nullable()->after('entry_kiosk_key');
        });
    }

    public function down(): void
    {
        Schema::table('entries', function (Blueprint $table) {
            $table->dropColumn(['entry_kiosk_key', 'exit_kiosk_key']);
        });
    }
};
