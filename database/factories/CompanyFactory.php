<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Company>
 */
class CompanyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // 8자리 숫자 – DART corp_code 가 형식에 맞도록 생성
            'corp_code' => $this->faker->unique()->regexify('[0-9]{8}'),

            // 6자리 숫자 – 한국거래소 상장 코드 형식
            'ticker' => $this->faker->unique()->regexify('[0-9]{6}'),

            // 한글 회사명
            'name_kr' => $this->faker->company(),

            // 영문 회사명(필요 없으면 null 허용)
            'name_en' => $this->faker->company(),
        ];
    }
}
