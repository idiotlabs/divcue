<?php

namespace Database\Factories;

use App\Models\Company;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Dividend>
 */
class DividendFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // 기준일(record_date)을 최근 1년 사이에서 랜덤 선택
        $recordDate = $this->faker->dateTimeBetween('-1 year', 'now');

        return [
            // FK 없으므로 정수 컬럼에 ID만 저장
            'company_id' => Company::factory(),

            // 1주당 현금배당액 – 200원~3,000원 범위
            'cash_amount' => $this->faker->randomFloat(2, 200, 3000),

            'record_date' => $recordDate,
            'ex_dividend_date' => Carbon::parse($recordDate)->subDays(2),
            'payment_date' => Carbon::parse($recordDate)->addDays(30),
        ];
    }
}
