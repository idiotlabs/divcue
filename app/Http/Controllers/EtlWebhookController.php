<?php

namespace App\Http\Controllers;

use App\Jobs\DividendAlertJob;
use App\Models\Company;
use App\Models\Dividend;
use Illuminate\Http\Request;

class EtlWebhookController extends Controller
{
    public function store(Request $request)
    {
        /* 1) 시크릿 헤더 검증 */
        abort_unless(
            $request->header('X-Etl-Secret') === env('ETL_SECRET'),
            401,
            'unauthorized'
        );

        /* 2) 최소 데이터 유효성 검사 */
        $data = $request->validate([
            'corp_code' => 'required|string',
            'ticker' => 'required|string',
            'name_kr' => 'required|string',
            'cash_amount' => 'required|numeric',
            'record_date' => 'required|date',
            'ex_dividend_date' => 'required|date',
            'payment_date' => 'required|date',
        ]);

        /* 3) 회사 upsert  (FK 없이 정수 id만 저장) */
        $company = Company::updateOrCreate(
            ['corp_code' => $data['corp_code']],
            [
                'ticker' => $data['ticker'],
                'name_kr' => $data['name_kr'],
            ]
        );

        /* 4) 배당 upsert  (중복 방지) */
        $dividend = Dividend::updateOrCreate(
            [
                'company_id' => $company->id,
                'record_date' => $data['record_date'],
            ],
            [
                'cash_amount' => $data['cash_amount'],
                'ex_dividend_date' => $data['ex_dividend_date'],
                'payment_date' => $data['payment_date'],
            ]
        );

        /* 5) 알림 Job 큐잉 */
        DividendAlertJob::dispatch($dividend->id);

        return response()->json(['ok' => true]);
    }
}
