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
            $table->string('rcept_no', 20)->nullable()->after('company_id');
            $table->index('rcept_no'); // 기본 인덱스명: dividends_rcept_no_index

            $table->text('report_nm')->nullable()->after('rcept_no');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dividends', function (Blueprint $table) {
            $table->dropIndex('dividends_rcept_no_index');
            $table->dropColumn(['report_nm', 'rcept_no']);
        });
    }
};
