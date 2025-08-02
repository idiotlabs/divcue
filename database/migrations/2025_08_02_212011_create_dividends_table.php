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
        Schema::create('dividends', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');   // FK 제거
            $table->decimal('cash_amount', 12, 2);
            $table->date('record_date');
            $table->date('ex_dividend_date');
            $table->date('payment_date');
            $table->timestamps();

            $table->unique(['company_id', 'record_date']);
            $table->index('company_id');               // 조회용 인덱스만
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dividends');
    }
};
