<?php

// 실제 ZIP에서 추출한 HTML 내용을 시뮬레이션
$htmlContent = file_get_contents('docs/20250811800073.xml');

// DartCollector의 zipToPlainText 로직 재현
function toUtf8($s) {
    if ($s === '') return $s;
    $enc = mb_detect_encoding($s, ['UTF-8', 'EUC-KR', 'CP949', 'ISO-8859-1'], true);
    if ($enc && $enc !== 'UTF-8') {
        $s = @iconv($enc, 'UTF-8//IGNORE', $s) ?: $s;
    }
    return $s;
}

function zipToPlainTextSimulation($data) {
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
    
    return $data;
}

$text = zipToPlainTextSimulation($htmlContent);
$text = toUtf8($text);

// 공백/제어문자 정리 (DartCollector와 동일)
$t = preg_replace('/[^\S\r\n]+/u', ' ', $text);
$t = preg_replace('/\h+/u', ' ', $t);
$t = preg_replace('/\R+/u', "\n", $t);

echo "=== 정제된 텍스트 일부 ===\n";
echo substr($t, 0, 1000) . "\n\n";

echo "=== 보통주 관련 텍스트 검색 ===\n";
if (preg_match('/보통주식.*?300/u', $t, $m)) {
    echo "매칭: " . $m[0] . "\n";
} else {
    echo "보통주식 매칭 실패\n";
}

echo "\n=== parseMoneySplit 시뮬레이션 ===\n";
function parseMoneySplit($t) {
    $common = null;
    $preferred = null;

    echo "패턴1 시도: '/보통주(?:식)?[^0-9]{0,30}([0-9][0-9,]{0,12})\s*(?:원)?/u'\n";
    if (preg_match('/보통주(?:식)?[^0-9]{0,30}([0-9][0-9,]{0,12})\s*(?:원)?/u', $t, $m)) {
        echo "패턴1 매칭 성공: " . $m[0] . " -> " . $m[1] . "\n";
        $common = (int)str_replace(',', '', $m[1]);
    } else {
        echo "패턴1 매칭 실패\n";
    }

    echo "\n패턴2 시도: '/(종류주|우선주)(?:식)?[^0-9]{0,30}([0-9][0-9,]{0,12})\s*(?:원)?/u'\n";
    if (preg_match('/(종류주|우선주)(?:식)?[^0-9]{0,30}([0-9][0-9,]{0,12})\s*(?:원)?/u', $t, $m)) {
        echo "패턴2 매칭 성공: " . $m[0] . " -> " . $m[2] . "\n";
        $preferred = (int)str_replace(',', '', $m[2]);
    } else {
        echo "패턴2 매칭 실패\n";
    }

    return [$common, $preferred];
}

list($common, $preferred) = parseMoneySplit($t);
echo "\n=== 최종 결과 ===\n";
echo "common: $common\n";
echo "preferred: $preferred\n";