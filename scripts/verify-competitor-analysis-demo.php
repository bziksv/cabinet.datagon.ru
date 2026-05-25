<?php

/**
 * Проверка POST /api/demo/analiz-konkurentov/run
 * php scripts/verify-competitor-analysis-demo.php [base_url]
 */

$base = $argv[1] ?? 'http://127.0.0.1:3002';

$payload = json_encode([
    'phrase' => 'купить отоскоп',
    'region_id' => '213',
], JSON_UNESCAPED_UNICODE);

$ch = curl_init(rtrim($base, '/') . '/api/demo/analiz-konkurentov/run');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 60,
]);
$body = curl_exec($ch);
$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP $code\n";
$decoded = json_decode((string) $body, true);
if (!is_array($decoded)) {
    echo $body . "\n";
    exit(1);
}

if ($code !== 200) {
    echo json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    exit(1);
}

$rows = $decoded['result']['serp']['rows'] ?? [];
echo 'phrase: ' . ($decoded['result']['phrase'] ?? '') . "\n";
echo 'serp rows: ' . count($rows) . "\n";
echo 'remaining: ' . ($decoded['remaining'] ?? '?') . "\n";
exit(count($rows) > 0 ? 0 : 1);
