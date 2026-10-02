<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kiosks', function (Blueprint $table) {
            // A kiosk is either an entry gate or an exit gate. Making it a property of the
            // kiosk means a scan no longer needs an armed direction chosen by hand.
            $table->string('type')->nullable()->after('key');
        });

        // Existing kiosks were built as "<lot> Entry" / "<lot> Exit" pairs, so the name and
        // key already say which way they face. Anything unrecognised stays NULL and is
        // refused at scan time until an operator sets it.
        DB::table('kiosks')->where('key', 'like', '%-entry')->update(['type' => 'entry']);
        DB::table('kiosks')->where('key', 'like', '%-exit')->update(['type' => 'exit']);
    }

    public function down(): void
    {
        Schema::table('kiosks', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
