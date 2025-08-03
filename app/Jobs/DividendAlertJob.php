<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class DividendAlertJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $dividendId)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('DividendAlertJob received', ['id' => $this->dividendId]);

        /** ➜ 여기서
         *   1. 사용자 alert_preferences 조회
         *   2. Mail / Telegram 전송
         *   3. 전송 결과 저장
         */
    }
}
