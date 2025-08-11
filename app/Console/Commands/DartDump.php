<?php

namespace App\Console\Commands;

use App\Services\DartCollector;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class DartDump extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dart:dump {rcept_no}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle(DartCollector $collector)
    {
        $rno = (string) $this->argument('rcept_no');
        $base = "dart_debug/{$rno}";
        @Storage::makeDirectory("dart_debug");

        $xml = Http::timeout(10)->get('https://opendart.fss.or.kr/api/document.xml', [
            'crtfc_key' => $key,
            'rcept_no'  => $rno,
        ]);
        $bin = $xml->body();

        $path = storage_path("app/dart_debug/{$rno}.zip");
        Storage::put("dart_debug/{$rno}.zip", $bin);

        $zip = new \ZipArchive();
        if ($zip->open($path) === true) {
            // OK: 추출/파싱 진행
            $zip->extractTo(storage_path("app/dart_debug/{$rno}"));
            $zip->close();
        } else {
            // 실패: document.xml 조회해 status/message 확인 (014/012/020 등)
        }

        return self::SUCCESS;
    }

    private function getApiKey(DartCollector $collector): string
    {
        // 리플렉션으로 private 프로퍼티 접근 (임시 디버그용)
        $ref = new \ReflectionClass($collector);
        $prop = $ref->getProperty('apiKey');
        $prop->setAccessible(true);
        return (string) $prop->getValue($collector);
    }
}
