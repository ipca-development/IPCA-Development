<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

$findingId = '6ac6bd3a-1cdb-4cc2-b268-c8d81777044e'; // <-- replace if needed
$url = "http://localhost:8888/compliance/findings/{$findingId}/actions/suggest-ai";

$payload = json_encode(new stdClass()); // empty JSON body

// Prefer cURL if available
if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 90,
    ]);

    $out  = curl_exec($ch);
    $err  = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo json_encode([
        'transport' => 'curl',
        'http_code' => $code,
        'curl_err'  => $err,
        'raw'       => $out,
    ]);
    exit;
}

// Fallback: use file_get_contents if cURL is missing
$context = stream_context_create([
    'http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: application/json\r\n",
        'content' => $payload,
        'timeout' => 90,
    ],
]);

$out = @file_get_contents($url, false, $context);

$httpCode = 0;
if (isset($http_response_header) && is_array($http_response_header)) {
    foreach ($http_response_header as $h) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) {
            $httpCode = (int)$m[1];
            break;
        }
    }
}

echo json_encode([
    'transport' => 'file_get_contents',
    'http_code' => $httpCode,
    'raw'       => $out,
    'php_warning' => error_get_last(),
]);