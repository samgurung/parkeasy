<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parking_lots', function (Blueprint $table) {
            $table->decimal('rate_two_wheeler', 8, 2)->default(10)->after('address');
            $table->decimal('rate_four_wheeler', 8, 2)->default(20)->after('rate_two_wheeler');
        });
    }

    public function down(): void
    {
        Schema::table('parking_lots', function (Blueprint $table) {
            $table->dropColumn(['rate_two_wheeler', 'rate_four_wheeler']);
        });
    }
};