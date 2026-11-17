<?php
header("Content-Type: application/json; charset=UTF-8"); // JSON ফরম্যাট নিশ্চিত করা

if (!isset($_GET['eiin']) || empty($_GET['eiin'])) {
    echo json_encode(["error" => "EIIN number is required"]);
    exit;
}

$eiin = $_GET['eiin'];
$url = "http://202.72.235.218:8082/api/v1/basic-info-one/info?eiinNo=" . urlencode($eiin);

$headers = [
    "Host: 202.72.235.218:8082",
    "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/113.0.0.0 Safari/537.36",
    "Accept-Encoding: gzip, deflate",
    "Cache-Control: max-age=0",
    "Content-Type: application/json",
    "Origin: http://202.72.235.217:3082",
    "X-Requested-With: com.xbrowser.play",
    "Referer: http://202.72.235.217:3082/",
    "Accept-Language: en-US,en;q=0.9"
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
curl_setopt($ch, CURLOPT_ENCODING, "");

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    // JSON ডাটা প্রিন্ট করার আগে সুন্দরভাবে ফরম্যাট করুন
    echo json_encode(json_decode($response), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode(["error" => "Failed to fetch data", "status_code" => $httpCode]);
}
?>
