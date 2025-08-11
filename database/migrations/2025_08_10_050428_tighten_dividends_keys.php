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
            // 기존 unique(company_id, record_date) 제거 (생성 시 기본명 규칙)
            $table->dropUnique(['company_id', 'record_date']);

            // rcept_no NOT NULL + 고유키
            $table->string('rcept_no', 20)->nullable(false)->change();
            $table->unique('rcept_no', 'dividends_rcept_no_unique');

            // 조회 인덱스
            $table->index(['company_id', 'record_date'], 'dividends_company_record_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dividends', function (Blueprint $table) {
            $table->dropUnique('dividends_rcept_no_unique');
            $table->dropIndex('dividends_company_record_idx');

            // 원복이 필요하면(선택)
            // $table->string('rcept_no', 20)->nullable()->change();
            $table->unique(['company_id', 'record_date']);
        });
    }
};
