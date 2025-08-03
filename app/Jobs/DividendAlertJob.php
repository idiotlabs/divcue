<?php

namespace App\Jobs;

use App\Models\Dividend;
use App\Models\User;
use App\Notifications\DividendAlertNotification;
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

        $dividend = Dividend::find($this->dividendId);
        if (!$dividend) return;

        // (임시) 모든 사용자에게 전송 — 이후 alert_preferences 필터로 교체
        User::whereNotNull('email')
            ->each(fn($user) => $user->notify(new DividendAlertNotification($dividend)));
    }
}
