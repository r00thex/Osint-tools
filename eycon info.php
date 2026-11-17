<?php
$CACHE_DIR = __DIR__ . '/cache';
$LOG_DIR   = __DIR__ . '/logs';
$CACHE_TTL = 300;
$RATE_LIMIT_WINDOW = 60;
$RATE_LIMIT_MAX = 30;

if (!is_dir($CACHE_DIR)) @mkdir($CACHE_DIR, 0755, true);
if (!is_dir($LOG_DIR))   @mkdir($LOG_DIR, 0755, true);

$E_AUTH      = getenv('EYE_E_AUTH') ?: 'c5f7d3f2-e7b0-4b42-aac0-07746f095d38';
$E_AUTH_C    = getenv('EYE_E_AUTH_C') ?: '40';
$E_AUTH_K    = getenv('EYE_E_AUTH_K') ?: 'PgdtSBeR0MumR7fO';
$USER_AGENT  = getenv('EYE_USER_AGENT') ?: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)';

function json_out($arr, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function log_line($msg) {
    global $LOG_DIR;
    $file = $LOG_DIR . '/lookup.log';
    $line = date('Y-m-d H:i:s') . ' ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . ' - ' . $msg . PHP_EOL;
    @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
}

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$ratefile = $LOG_DIR . '/rate_' . md5($ip) . '.json';
$now = time();
$rate = ['ts' => $now, 'count' => 0];
if (file_exists($ratefile)) {
    $read = json_decode(@file_get_contents($ratefile), true);
    if ($read && isset($read['ts']) && isset($read['count'])) {
        $rate = $read;
        if ($now - $rate['ts'] > $RATE_LIMIT_WINDOW) {
            $rate = ['ts' => $now, 'count' => 0];
        }
    }
}
$rate['count'] = ($rate['count'] ?? 0) + 1;
file_put_contents($ratefile, json_encode($rate), LOCK_EX);
if ($rate['count'] > $RATE_LIMIT_MAX) {
    log_line("rate_limit_exceeded ({$rate['count']})");
    json_out(['error' => 'rate_limited', 'message' => 'Too many requests. Try again later.'], 429);
}

$number = $_GET['number'] ?? $_POST['number'] ?? null;
if (!$number) {
    json_out(['error' => 'missing_parameter', 'message' => 'Please provide ?number=...'], 400);
}
$sanitized = preg_replace('/\s+/', '', $number);
if (!preg_match('/^\+?\d+$/', $sanitized)) {
    json_out(['error' => 'invalid_number', 'message' => 'Number must contain only digits and optional leading +'], 400);
}

$cacheKey = 'eye_' . md5($sanitized);
$cacheFile = $CACHE_DIR . '/' . $cacheKey . '.json';
if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $CACHE_TTL)) {
    $cached = json_decode(@file_get_contents($cacheFile), true);
    if ($cached) {
        $name = $cached['name'] ?? null;
        $out = [
            "Number" => $sanitized,
            "Name"   => $name ?: "Not Found"
        ];
        log_line("cache_hit {$sanitized}");
        json_out($out, 200);
    }
}

$params = http_build_query([
    'cli' => $sanitized,
    'lang' => 'en',
    'is_callerid' => 'true',
    'is_ic' => 'true',
    'cv' => 'vc_672_vn_4.2025.10.17.1932_a',
    'requestApi' => 'URLconnection',
    'source' => 'MenifaFragment'
]);
$url = 'https://api.eyecon-app.com/app/getnames.jsp?' . $params;

$headers = [
    'User-Agent: ' . $USER_AGENT,
    'Accept: application/json',
    'e-auth-v: e1',
    'e-auth: ' . $E_AUTH,
    'e-auth-c: ' . $E_AUTH_C,
    'e-auth-k: ' . $E_AUTH_K,
    'Accept-Charset: UTF-8',
    'Content-Type: application/x-www-form-urlencoded; charset=utf-8',
    'Connection: Keep-Alive',
    'Host: api.eyecon-app.com'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE) ?: 0;
$curlErr = curl_error($ch);
curl_close($ch);

if ($response === false || $curlErr) {
    log_line("request_failed {$sanitized} curlerr: {$curlErr}");
    $out = [
        "Number" => $sanitized,
        "Name"   => "Not Found"
    ];
    json_out($out, 200);
}

$decoded = json_decode($response, true);
$name = null;

if (is_array($decoded)) {
    if (isset($decoded[0]['name'])) {
        $name = trim($decoded[0]['name']);
    } elseif (isset($decoded['data'][0]['name'])) {
        $name = trim($decoded['data'][0]['name']);
    }
}

if (!$name) {
    $name = "Not Found";
}

@file_put_contents($cacheFile, json_encode(['name' => $name], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);

log_line("success {$sanitized} http={$httpCode} name=" . ($name === "Not Found" ? 'NOT_FOUND' : $name));

$out = [
    "Number" => $sanitized,
    "Name"   => $name
];

json_out($out, 200);
