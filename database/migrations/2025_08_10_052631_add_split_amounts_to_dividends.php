<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('dividends', function (Blueprint $table) {
            // 주당배당(보통/종류) 분리
            $table->integer('cash_amount_common')->nullable()->after('cash_amount');
            $table->integer('cash_amount_preferred')->nullable()->after('cash_amount_common');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dividends', function (Blueprint $table) {
            $table->dropColumn(['cash_amount_common', 'cash_amount_preferred']);
        });
    }
};
