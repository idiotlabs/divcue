<?php

namespace Tests\Feature;

use App\Jobs\DividendAlertJob;
use App\Models\Company;
use App\Models\Dividend;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EtlDividendTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    /** 정상적인 배당금 데이터로 API 호출 시 회사와 배당금이 생성되고 알림 Job이 큐에 추가되는지 테스트 */
    public function test_successful_dividend_creation()
    {
        $payload = [
            'corp_code' => '005930',
            'ticker' => '005930',
            'name_kr' => '삼성전자',
            'cash_amount' => 361,
            'record_date' => '2024-12-31',
            'ex_dividend_date' => '2024-12-30',
            'payment_date' => '2025-04-17',
        ];

        $response = $this->postJson('/api/etl/dividend', $payload, [
            'X-Etl-Secret' => config('services.etl_secret', 'test-secret-key-for-testing'),
        ]);

        $response->assertStatus(200)
            ->assertJson(['ok' => true]);

        $this->assertDatabaseHas('companies', [
            'corp_code' => '005930',
            'ticker' => '005930',
            'name_kr' => '삼성전자',
        ]);

        $company = Company::where('corp_code', '005930')->first();
        $this->assertDatabaseHas('dividends', [
            'company_id' => $company->id,
            'cash_amount' => 361,
            'record_date' => '2024-12-31',
            'ex_dividend_date' => '2024-12-30',
            'payment_date' => '2025-04-17',
        ]);

        Queue::assertPushed(DividendAlertJob::class);
    }

    /** 기존 회사가 있을 때 새로운 데이터로 회사 정보가 업데이트되는지 테스트 */
    public function test_company_update_when_exists()
    {
        $company = Company::create([
            'corp_code' => '005930',
            'ticker' => '005930_OLD',
            'name_kr' => '삼성전자_OLD',
        ]);

        $payload = [
            'corp_code' => '005930',
            'ticker' => '005930',
            'name_kr' => '삼성전자',
            'cash_amount' => 361,
            'record_date' => '2024-12-31',
            'ex_dividend_date' => '2024-12-30',
            'payment_date' => '2025-04-17',
        ];

        $response = $this->postJson('/api/etl/dividend', $payload, [
            'X-Etl-Secret' => config('services.etl_secret', 'test-secret-key-for-testing'),
        ]);

        $response->assertStatus(200);

        $company->refresh();
        $this->assertEquals('005930', $company->ticker);
        $this->assertEquals('삼성전자', $company->name_kr);
    }

    /** 동일한 회사와 기준일의 배당금이 이미 있을 때 배당금 정보가 업데이트되고 중복 생성되지 않는지 테스트 */
    public function test_dividend_update_when_exists()
    {
        $company = Company::create([
            'corp_code' => '005930',
            'ticker' => '005930',
            'name_kr' => '삼성전자',
        ]);

        $dividend = Dividend::create([
            'company_id' => $company->id,
            'cash_amount' => 300,
            'record_date' => '2024-12-31',
            'ex_dividend_date' => '2024-12-30',
            'payment_date' => '2025-04-16',
        ]);

        $payload = [
            'corp_code' => '005930',
            'ticker' => '005930',
            'name_kr' => '삼성전자',
            'cash_amount' => 361,
            'record_date' => '2024-12-31',
            'ex_dividend_date' => '2024-12-30',
            'payment_date' => '2025-04-17',
        ];

        $response = $this->postJson('/api/etl/dividend', $payload, [
            'X-Etl-Secret' => config('services.etl_secret', 'test-secret-key-for-testing'),
        ]);

        $response->assertStatus(200);

        $dividend->refresh();
        $this->assertEquals(361, $dividend->cash_amount);
        $this->assertEquals('2025-04-17', $dividend->payment_date);

        $this->assertEquals(1, Dividend::count());
    }

    /** X-Etl-Secret 헤더가 없을 때 401 인증 오류가 발생하고 데이터가 생성되지 않는지 테스트 */
    public function test_unauthorized_without_secret_header()
    {
        $payload = [
            'corp_code' => '005930',
            'ticker' => '005930',
            'name_kr' => '삼성전자',
            'cash_amount' => 361,
            'record_date' => '2024-12-31',
            'ex_dividend_date' => '2024-12-30',
            'payment_date' => '2025-04-17',
        ];

        $response = $this->postJson('/api/etl/dividend', $payload);

        $response->assertStatus(401);
        $this->assertEquals(0, Company::count());
        $this->assertEquals(0, Dividend::count());
        Queue::assertNothingPushed();
    }

    /** 잘못된 X-Etl-Secret 헤더로 요청할 때 401 인증 오류가 발생하는지 테스트 */
    public function test_unauthorized_with_wrong_secret()
    {
        $payload = [
            'corp_code' => '005930',
            'ticker' => '005930',
            'name_kr' => '삼성전자',
            'cash_amount' => 361,
            'record_date' => '2024-12-31',
            'ex_dividend_date' => '2024-12-30',
            'payment_date' => '2025-04-17',
        ];

        $response = $this->postJson('/api/etl/dividend', $payload, [
            'X-Etl-Secret' => 'wrong-secret',
        ]);

        $response->assertStatus(401);
    }

    /** 필수 필드들이 누락되었을 때 422 유효성 검사 오류가 발생하는지 테스트 */
    public function test_validation_required_fields()
    {
        $response = $this->postJson('/api/etl/dividend', [], [
            'X-Etl-Secret' => config('services.etl_secret', 'test-secret-key-for-testing'),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'corp_code',
                'ticker',
                'name_kr',
                'cash_amount',
                'record_date',
                'ex_dividend_date',
                'payment_date',
            ]);
    }

    /** 필드 타입이 올바르지 않을 때 422 유효성 검사 오류가 발생하는지 테스트 */
    public function test_validation_field_types()
    {
        $payload = [
            'corp_code' => 123,
            'ticker' => 456,
            'name_kr' => 789,
            'cash_amount' => 'not-numeric',
            'record_date' => 'invalid-date',
            'ex_dividend_date' => 'invalid-date',
            'payment_date' => 'invalid-date',
        ];

        $response = $this->postJson('/api/etl/dividend', $payload, [
            'X-Etl-Secret' => config('services.etl_secret', 'test-secret-key-for-testing'),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'corp_code',
                'ticker', 
                'name_kr',
                'cash_amount',
                'record_date',
                'ex_dividend_date',
                'payment_date',
            ]);
    }

    /** 같은 회사의 서로 다른 기준일 배당금들이 모두 생성되고 각각 알림 Job이 큐에 추가되는지 테스트 */
    public function test_multiple_dividends_same_company_different_dates()
    {
        $company = Company::create([
            'corp_code' => '005930',
            'ticker' => '005930',
            'name_kr' => '삼성전자',
        ]);

        $payload1 = [
            'corp_code' => '005930',
            'ticker' => '005930',
            'name_kr' => '삼성전자',
            'cash_amount' => 361,
            'record_date' => '2024-06-30',
            'ex_dividend_date' => '2024-06-28',
            'payment_date' => '2024-08-16',
        ];

        $payload2 = [
            'corp_code' => '005930',
            'ticker' => '005930',
            'name_kr' => '삼성전자',
            'cash_amount' => 361,
            'record_date' => '2024-12-31',
            'ex_dividend_date' => '2024-12-30',
            'payment_date' => '2025-04-17',
        ];

        $this->postJson('/api/etl/dividend', $payload1, [
            'X-Etl-Secret' => config('services.etl_secret', 'test-secret-key-for-testing'),
        ])->assertStatus(200);

        $this->postJson('/api/etl/dividend', $payload2, [
            'X-Etl-Secret' => config('services.etl_secret', 'test-secret-key-for-testing'),
        ])->assertStatus(200);

        $this->assertEquals(1, Company::count());
        $this->assertEquals(2, Dividend::count());
        
        Queue::assertPushed(DividendAlertJob::class, 2);
    }

    /** DividendAlertJob이 올바른 배당금 ID로 큐에 추가되는지 테스트 */
    public function test_job_dispatched_with_correct_dividend_id()
    {
        $payload = [
            'corp_code' => '005930',
            'ticker' => '005930',
            'name_kr' => '삼성전자',
            'cash_amount' => 361,
            'record_date' => '2024-12-31',
            'ex_dividend_date' => '2024-12-30',
            'payment_date' => '2025-04-17',
        ];

        $this->postJson('/api/etl/dividend', $payload, [
            'X-Etl-Secret' => config('services.etl_secret', 'test-secret-key-for-testing'),
        ]);

        $dividend = Dividend::first();
        
        Queue::assertPushed(DividendAlertJob::class, function ($job) use ($dividend) {
            return $job->dividendId === $dividend->id;
        });
    }
}