<?php

namespace App\Console\Commands;

use App\Models\EtlState;
use App\Services\DartCollector;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class PollDividends extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dividends:poll {--from=} {--to=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Poll DART dividends and enqueue notifications';

    /**
     * Execute the console command.
     */
    public function handle(DartCollector $collector)
    {
        // 동시 실행 방지(5분 락)
        $lock = Cache::lock('dividends:poll', 300);
        if (!$lock->get()) {
            $this->info('Another poll is running. Skipping.');
            return Command::SUCCESS;
        }

        try {
            $from = $this->option('from') ?? now('Asia/Seoul')->subDay()->format('Ymd');
            $to   = $this->option('to')   ?? now('Asia/Seoul')->format('Ymd');
            $last = (int) (EtlState::getValue('last_rcv_no', 0));

            $count = $collector->run($from, $to, $last);
            $this->info("Inserted/queued: {$count}");
            return Command::SUCCESS;
        } finally {
            optional($lock)->release();
        }
    }
}
