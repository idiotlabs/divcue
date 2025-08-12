<?php
// app/Services/DartCollector.php

namespace App\Services;

use App\Jobs\DividendAlertJob;
use App\Models\Company;
use App\Models\Dividend;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DartCollector
{
    private const LIST_URL = 'https://opendart.fss.or.kr/api/list.json';
    private const DOC_XML_URL = 'https://opendart.fss.or.kr/api/document.xml'; // zip URL 조회
    private const DOC_BIN_URL = 'https://opendart.fss.or.kr/api/document';     // zip 바이너리 직접
    private const PAGE_SIZE = 100;

    // 하드코딩 원하셨죠 🙂
    private string $apiKey = '4a947b2d03c602ddf6c6ed465f69cb3276ad6c29';

    // 배당 공시 판별
    private string $divRegex = '/(현금[ㆍ··]?\s*현물?|현금)\s*배당(결정|결의|\()/u';
    private array $skipWords = ['주주명부폐쇄', '기준일']; // 안내성 공시 제외

    public function run(?string $from = null, ?string $to = null, ?int $lastRcvNo = null): int
    {
        $from = $from ?? now('Asia/Seoul')->subDays(3)->format('Ymd');
        $to = $to ?? now('Asia/Seoul')->format('Ymd');

        $page = 1;
        $handled = 0;

        $stats = ['pages' => 0, 'items' => 0, 'skip_word' => 0, 'regex_miss' => 0, 'fetch_fail' => 0, 'parse_miss' => 0, 'inserted' => 0];

        // 샘플 저장을 위한 플래그
        $sampleSaved = false;
        $zipSampleSaved = false;

        while (true) {
            $res = Http::timeout(12)->get(self::LIST_URL, [
                'crtfc_key' => $this->apiKey,
                'bgn_de' => $from,
                'end_de' => $to,
                'page_no' => $page,
                'page_count' => self::PAGE_SIZE,
            ])->json();

            $status = $res['status'] ?? null;
            if (!in_array($status, ['000', '013'], true)) {
                Log::error('DART list error', $res ?? []);
                break;
            }

            $items = $res['list'] ?? [];
            if (!$items) break;

            // 첫 번째 페이지 결과를 샘플로 저장
//            if (!$sampleSaved && $page === 1) {
//                $this->saveSampleListResult([
//                    'timestamp' => now('Asia/Seoul')->toISOString(),
//                    'request_params' => [
//                        'bgn_de' => $from,
//                        'end_de' => $to,
//                        'page_no' => $page,
//                        'page_count' => self::PAGE_SIZE,
//                    ],
//                    'response' => $res
//                ]);
//                $sampleSaved = true;
//            }

            $stats['pages']++;
            $stats['items'] += count($items);

            foreach ($items as $it) {
                $title = trim($it['report_nm'] ?? '');

                // 1) 스킵워드
                if (str_contains($title, $this->skipWords[0]) || str_contains($title, $this->skipWords[1])) {
                    $stats['skip_word']++;
                    continue;
                }
                // 2) 배당결정 정규식
                if (!preg_match($this->divRegex, $title)) {
                    $stats['regex_miss']++;
                    continue;
                }

                $rceptNo = (string)($it['rcept_no'] ?? '');
                if ($rceptNo === '') {
                    $stats['regex_miss']++;
                    continue;
                }

                // ─ (1) 문서 ZIP 가져오기
                $zipBytes = $this->fetchDocumentZip($rceptNo);
                if (!$zipBytes) {
                    $stats['fetch_fail']++;
                    continue;
                }

                // 첫 번째 성공적인 ZIP을 샘플로 저장
//                if (!$zipSampleSaved) {
//                    $this->saveSampleZipFile($rceptNo, $zipBytes);
//                    $zipSampleSaved = true;
//                }

                // ─ (2) 본문에서 값 파싱
                $parsed = $this->parseZipForFields($zipBytes);
                if (empty($parsed['record_date'])) {
                    $stats['parse_miss']++;
                    continue;
                }

                // ─ (3) 회사 보장
                $company = Company::firstOrCreate(
                    ['corp_code' => $it['corp_code']],
                    ['ticker' => $it['stock_code'] ?? null, 'name_kr' => $it['corp_name'] ?? null]
                );

                // ─ (4) DB upsert: 회사 + record_date 기준
                $dividend = Dividend::updateOrCreate(
                    ['rcept_no' => $rceptNo],  // ← 고유키 기준
                    [
                        'company_id' => $company->id,
                        'report_nm' => $title,
                        'record_date' => $parsed['record_date'],
                        'payment_date' => $parsed['payment_date'] ?? null,
                        'ex_dividend_date' => $parsed['ex_dividend_date'] ?? null,
                        'cash_amount' => $parsed['cash_amount'] ?? 0,  // 이후 보강 2에서 분리 예정
                        'cash_amount_common' => $parsed['cash_amount_common'] ?? null,
                        'cash_amount_preferred' => $parsed['cash_amount_preferred'] ?? null,
                    ]
                );

                // ─ (5) 알림 디스패치
                dispatch(new DividendAlertJob($dividend->id));
                $stats['inserted']++;

                $handled++;
            }

            if (count($items) < self::PAGE_SIZE) break;
            $page++;
        }

        Log::info("DartCollector done handled={$handled}");
        Log::info('DartCollector stats', $stats);

        return $handled;
    }

    /**
     * DART 문서 ZIP 바이트 가져오기.
     * 1) document.xml에서 다운로드 URL 얻기 → 실패 시 2) document 바이너리 직접
     */
    private function fetchDocumentZip(string $rceptNo): ?string
    {
        try {
            // 1) XML에서 file_url 시도
            $xml = Http::timeout(10)->get(self::DOC_XML_URL, [
                'crtfc_key' => $this->apiKey,
                'rcept_no' => $rceptNo,
            ])->body();

            return $xml;

//            if ($xml && preg_match('~<file_url>\s*(https?://[^<]+)\s*</file_url>~i', $xml, $m)) {
//                $url = html_entity_decode($m[1], ENT_QUOTES);
//                $bin = Http::timeout(15)->get($url)->body();
//                if ($bin && strlen($bin) > 100) return $bin;
//            }
//
//            // 2) 바이너리 직접
//            $bin = Http::timeout(15)->get(self::DOC_BIN_URL, [
//                'crtfc_key' => $this->apiKey,
//                'rcept_no' => $rceptNo,
//            ])->body();
//
//            return ($bin && strlen($bin) > 100) ? $bin : null;
        } catch (\Throwable $e) {
            Log::error("fetchDocumentZip error {$rceptNo}: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * ZIP 안의 html/xml/txt 파일을 읽어 텍스트로 합친 후
     * 기준일/지급일/주당배당금(원) 추출.
     */
    private function parseZipForFields(string $zipBytes): array
    {
        $text = $this->zipToPlainText($zipBytes);
        $text = $this->toUtf8($text);

        // 공백/제어문자 정리
        $t = preg_replace('/[^\S\r\n]+/u', ' ', $text);  // 다중 공백 → 단일 공백
        $t = preg_replace('/\h+/u', ' ', $t);            // 수평 공백 정규화
        $t = preg_replace('/\R+/u', "\n", $t);           // 개행 정규화

        // 라벨 확장
        $recordLabels = ['배당기준일', '기준일', '주주확정기준일', '권리주주확정일', '주주명부폐쇄기준일', '기준일자'];
        $payLabels = ['배당금지급 예정일자', '배당금지급예정일자', '지급예정일', '지급일', '지급개시일', '배당금지급일', '배당지급예정일'];
        $exLabels = ['배당락일', '권리락일'];

        $record = $this->firstDateAfter($t, $recordLabels);
        $pay = $this->firstDateAfter($t, $payLabels);
        $exdiv = $this->firstDateAfter($t, $exLabels);

        if (!$exdiv && $record) {
            $exdiv = \Carbon\Carbon::createFromFormat('Y-m-d', $record)->subDay();
        }

        // 금액(주당) — '원'이 뒤에 없고, 테이블 셀에 숫자만 있는 케이스 지원
//        $cash = $this->firstMoneyRobust($t);

        // 2) 보통/종류 분리 추출
        [$common, $preferred] = $this->parseMoneySplit($t);

        // 3) 호환용 cash_amount 결정(보통주 우선, 없으면 종류주)
        $cash = $common ?? $preferred ?? 0;

        return [
            'record_date' => $record,
            'payment_date' => $pay,
            'ex_dividend_date' => $exdiv,
            'cash_amount' => $cash,
            'cash_amount_common' => $common,
            'cash_amount_preferred' => $preferred,
        ];
    }

    private function zipToPlainText(string $zipBytes): string
    {
        // 메모리 임시 파일에 써서 ZipArchive 사용
        $tmp = tmpfile();
        fwrite($tmp, $zipBytes);
        $meta = stream_get_meta_data($tmp);
        $path = $meta['uri'];

        $txt = '';
        $zip = new \ZipArchive();
        if ($zip->open($path) === true) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (!preg_match('/\.(html?|xml|txt)$/i', $name)) continue;
                $data = $zip->getFromIndex($i);
                if (!$data) continue;

                // HTML/XML이면 태그 제거 - 더 강력한 태그 제거
                if (preg_match('/\.(html?|xml)$/i', $name)) {
                    // CSS 스타일 블록 완전 제거
                    $data = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $data);
                    // script 블록 제거
                    $data = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $data);
                    // head 태그 전체 제거 (CSS가 남아있을 수 있음)
                    $data = preg_replace('/<head[^>]*>.*?<\/head>/is', '', $data);

                    // HTML 태그를 공백으로 대체하여 완전히 제거
                    $data = strip_tags($data);

                    // HTML 엔티티 디코드
                    $data = html_entity_decode($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');

                    // 여러 공백을 하나로 정리
                    $data = preg_replace('/\s+/', ' ', $data);
                    $data = trim($data);
                }

                $txt .= "\n" . $data;
            }
            $zip->close();
        }
        fclose($tmp);
        return $txt;
    }

    private function toUtf8(string $s): string
    {
        if ($s === '') return $s;
        // 흔한 euc-kr/ks_c_5601-1987 보정
        $enc = mb_detect_encoding($s, ['UTF-8', 'EUC-KR', 'CP949', 'ISO-8859-1'], true);
        if ($enc && $enc !== 'UTF-8') {
            $s = @iconv($enc, 'UTF-8//IGNORE', $s) ?: $s;
        }
        return $s;
    }

    private function firstDateAfter(string $text, array $labels): ?string
    {
        // 더 유연한 날짜 패턴들
        $datePatterns = [
            // YYYY-MM-DD 형식 (현재 HTML에 있는 형식)
            '(20\d{2})-(\d{1,2})-(\d{1,2})',
            // 기존 패턴들
            '(20\d{2})(?:[.\-\/년]\s*)(\d{1,2})(?:[.\-\/월]\s*)(\d{1,2})(?:\s*일)?',
            // YYYY.MM.DD 형식
            '(20\d{2})\.(\d{1,2})\.(\d{1,2})',
            // YYYY/MM/DD 형식
            '(20\d{2})\/(\d{1,2})\/(\d{1,2})'
        ];

        foreach ($labels as $label) {
            foreach ($datePatterns as $datePattern) {
                // 라벨 뒤에 최대 200자까지 허용하여 날짜 검색 (기존 100자에서 확대)
                // 줄바꿈과 공백을 모두 허용
                if (preg_match('/' . preg_quote($label, '/') . '[\s\S]{0,200}?' . $datePattern . '/u', $text, $m)) {
                    return $this->ymd($m[1], $m[2], $m[3]);
                }
            }
        }
        return null;
    }

    private function firstMoneyRobust(string $text): ?int
    {
        // body 태그 이후의 내용만 추출 (CSS 부분 제외)
        if (preg_match('/<body[^>]*>(.*?)<\/body>/is', $text, $bodyMatch)) {
            $text = strip_tags($bodyMatch[1]);
            $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $text = preg_replace('/\s+/', ' ', $text);
        }

//        dump("정제된 텍스트 일부:", substr($text, 0, 500));

        // 우선: "1주당 배당금(원)" 라벨 뒤 첫 숫자
        if (preg_match('/1주당\s*배당금\s*\(원\)[^0-9]*([0-9][0-9,]*)/u', $text, $m)) {
//            dump("패턴1 매칭:", $m[1]);
            return (int)str_replace(',', '', $m[1]);
        }

        // 보통주 행 기준
        if (preg_match('/보통주식[^0-9]*([0-9][0-9,]*)/u', $text, $m)) {
//            dump("패턴2 매칭:", $m[1]);
            return (int)str_replace(',', '', $m[1]);
        }

        // HTML 테이블 구조를 고려한 패턴
        if (preg_match('/1주당\s*배당금.*?보통주식.*?([0-9][0-9,]*)/u', $text, $m)) {
//            dump("패턴3 매칭:", $m[1]);
            return (int)str_replace(',', '', $m[1]);
        }

//        dump("매칭된 패턴 없음");
        return null;
    }

    private function ymd($y, $m, $d): string
    {
        return \Carbon\Carbon::createFromDate((int)$y, (int)$m, (int)$d)->format('Y-m-d');
    }

    /**
     * 텍스트에서 보통주/종류주 주당배당(원)을 각각 찾아냄.
     * - "보통주식 ... 300" / "종류주식 ... 350"
     * - "우선주 ... 350" 케이스도 함께 커버
     * - "원" 단위가 생략된 케이스도 있어 숫자만도 허용
     */
    private function parseMoneySplit(string $t): array
    {
        $common = null;
        $preferred = null;

        // 정규식 특징:
        // - '보통주' 키워드 주변 0~30자 범위에서 숫자(천단위 콤마 허용) 캡처
        // - 뒤에 '원'은 있어도 되고 없어도 됨
        // - 비 greedy 매칭으로 첫 숫자 우선
        if (preg_match('/보통주(?:식)?[^0-9]{0,30}([0-9][0-9,]{0,12})\s*(?:원)?/u', $t, $m)) {
            $common = (int)str_replace(',', '', $m[1]);
        }

        // '종류주' 또는 '우선주'로 케이스 커버 - "-" 제외하고 실제 숫자만
        if (preg_match('/(종류주|우선주)(?:식)?[^0-9\-]{0,20}([0-9][0-9,]{0,12})\s*(?:원)?/u', $t, $m)) {
            $preferred = (int)str_replace(',', '', $m[2]);
        }

        // 백업: 표 머리글이 "1주당 배당금(원)"이고 행이 두 줄로 나뉘는 문서
        // 예: 1주당 배당금(원) [보통주식] 300 / [종류주식] 350
        if ($common === null && preg_match('/1주당\s*배당금[^\n]*\n?[^\n]*보통주[^\d]*([0-9][0-9,]*)/u', $t, $m)) {
            $common = (int)str_replace(',', '', $m[1]);
        }
        if ($preferred === null && preg_match('/1주당\s*배당금[^\n]*\n?[^\n]*(종류주|우선주)[^\d\-]*([0-9][0-9,]*)/u', $t, $m)) {
            $preferred = (int)str_replace(',', '', $m[2]);
        }

        return [$common, $preferred];
    }

    private function saveSampleListResult(array $data): void
    {
        $filename = 'list_sample_' . now('Asia/Seoul')->format('Ymd_His') . '.json';
        $filepath = base_path('docs/' . $filename);

        file_put_contents($filepath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        Log::info("LIST_URL 샘플 저장됨: {$filepath}");
    }

    private function saveSampleZipFile(string $rceptNo, string $zipBytes): void
    {
        $timestamp = now('Asia/Seoul')->format('Ymd_His');
        $filename = "zip_sample_{$timestamp}_{$rceptNo}.zip";
        $filepath = base_path('docs/' . $filename);

        file_put_contents($filepath, $zipBytes);
        Log::info("ZIP 샘플 저장됨: {$filepath}");
    }
}
