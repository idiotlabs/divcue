<?php

namespace App\Jobs;

use App\Models\AlertPreference;
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
        $dividend = Dividend::with('company')->find($this->dividendId);
        if (!$dividend) return;

        // (1) 이 회사에 대한 알림을 구독한 사용자 + 최소 금액 조건 충족
        $targets = AlertPreference::where('company_id', $dividend->company_id)
            ->where('min_amount', '<=', $dividend->cash_amount)
            ->pluck('user_id');

        // (2) Eloquent 한 번만 호출해 User 컬렉션 가져오기
        User::whereIn('id', $targets)
            ->each(fn($user) => $user->notify(new DividendAlertNotification($dividend)));
    }
}
