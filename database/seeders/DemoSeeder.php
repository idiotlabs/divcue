<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Dividend;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Company::factory(10)          // ① 회사 10곳 생성
        ->create()
            ->each(function ($company) {   // ② 각 회사당 배당 10건씩
                Dividend::factory(10)->create([
                    'company_id' => $company->id,
                ]);
            });
    }
}
