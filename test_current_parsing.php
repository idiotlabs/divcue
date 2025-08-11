<?php
require_once 'vendor/autoload.php';

use App\Services\DartCollector;

// ZIP 파일에서 실제 DartCollector로 파싱 테스트
$zipPath = 'docs/zip_sample_20250811_144444_20250811800073.zip';

if (!file_exists($zipPath)) {
    die("ZIP 파일이 없습니다: $zipPath\n");
}

$zipBytes = file_get_contents($zipPath);

// DartCollector 인스턴스 생성 및 reflection을 통한 private 메소드 접근
$collector = new DartCollector();
$reflection = new ReflectionClass($collector);

// parseZipForFields 메소드 호출
$parseMethod = $reflection->getMethod('parseZipForFields');
$parseMethod->setAccessible(true);

echo "=== DartCollector parseZipForFields 테스트 ===\n";
try {
    $result = $parseMethod->invokeArgs($collector, [$zipBytes]);
    echo "파싱 결과:\n";
    print_r($result);
    
    // 개별 필드 확인
    echo "\n=== 개별 필드 검증 ===\n";
    echo "record_date: " . ($result['record_date'] ?? 'null') . "\n";
    echo "payment_date: " . ($result['payment_date'] ?? 'null') . "\n"; 
    echo "ex_dividend_date: " . ($result['ex_dividend_date'] ?? 'null') . "\n";
    echo "cash_amount: " . ($result['cash_amount'] ?? 'null') . "\n";
    echo "cash_amount_common: " . ($result['cash_amount_common'] ?? 'null') . "\n";
    echo "cash_amount_preferred: " . ($result['cash_amount_preferred'] ?? 'null') . "\n";
    
} catch (Exception $e) {
    echo "파싱 에러: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

// zipToPlainText로 실제 텍스트도 확인
$textMethod = $reflection->getMethod('zipToPlainText');
$textMethod->setAccessible(true);

echo "\n=== zipToPlainText 결과 ===\n";
try {
    $plainText = $textMethod->invokeArgs($collector, [$zipBytes]);
    echo "추출된 텍스트 길이: " . strlen($plainText) . "\n";
    echo "텍스트 일부:\n" . substr($plainText, 0, 500) . "\n";
    
    // 중요 키워드들 검색
    echo "\n=== 키워드 검색 ===\n";
    echo "보통주식 포함: " . (strpos($plainText, '보통주식') !== false ? 'YES' : 'NO') . "\n";
    echo "300 포함: " . (strpos($plainText, '300') !== false ? 'YES' : 'NO') . "\n";
    echo "2025-06-30 포함: " . (strpos($plainText, '2025-06-30') !== false ? 'YES' : 'NO') . "\n";
    
} catch (Exception $e) {
    echo "텍스트 추출 에러: " . $e->getMessage() . "\n";
}