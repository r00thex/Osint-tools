<?php
header('Content-Type: application/json');

if (!isset($_GET['url'])) {
    echo json_encode(["error" => "Missing 'url' parameter."]);
    exit;
}

function extract_channel_identifier($url) {
    $parsed = parse_url($url);
    if (!isset($parsed['path'])) return false;

    $path = trim($parsed['path'], '/');
    $parts = explode('/', $path);

    if (strpos($path, '@') === 0) {
        return ['type' => 'handle', 'value' => ltrim($path, '@')];
    }

    if ($parts[0] === 'channel' && isset($parts[1])) {
        return ['type' => 'id', 'value' => $parts[1]];
    }

    if ($parts[0] === 'user' && isset($parts[1])) {
        return ['type' => 'user', 'value' => $parts[1]];
    }

    return false;
}

function get_channel_id_from_html($url) {
    $html = @file_get_contents($url);
    if (!$html) return false;

    if (preg_match('/"channelId":"(UC[\w-]+)"/', $html, $matches)) {
        return $matches[1];
    }
    return false;
}

function resolve_channel_id($type, $value) {
    $base_url = "https://www.youtube.com/";
    $lookup_url = match ($type) {
        'handle' => $base_url . '@' . $value,
        'user'   => $base_url . 'user/' . $value,
        default  => null,
    };

    if (!$lookup_url) return false;

    return get_channel_id_from_html($lookup_url);
}

$input_url = $_GET['url'];
$identifier_data = extract_channel_identifier($input_url);

if (!$identifier_data) {
    echo json_encode(["error" => "Invalid YouTube channel URL."]);
    exit;
}

$channel_id = false;

switch ($identifier_data['type']) {
    case 'id':
        $channel_id = $identifier_data['value'];
        break;
    case 'handle':
    case 'user':
        $channel_id = resolve_channel_id($identifier_data['type'], $identifier_data['value']);
        break;
}

if (!$channel_id) {
    echo json_encode(["error" => "Unable to resolve channel ID."]);
    exit;
}

$api_url = "https://ytapi.apps.mattw.io/v3/channels?key=foo1&quotaUser=auto123&part=id,snippet,statistics,brandingSettings,contentDetails,status,topicDetails&id={$channel_id}";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_ENCODING, 'gzip, deflate, br');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'User-Agent: Mozilla/5.0',
    'Accept: application/json',
    'origin: https://mattw.io',
    'referer: https://mattw.io/',
    'x-requested-with: com.xbrowser.play'
]);
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);

if (!isset($data['items'][0])) {
    echo json_encode(["error" => "Channel data not found."]);
    exit;
}

$item = $data['items'][0];

$result = [
    "channel_id"        => $item['id'],
    "title"             => $item['snippet']['title'] ?? '',
    "description"       => $item['snippet']['description'] ?? '',
    "custom_url"        => $item['snippet']['customUrl'] ?? '',
    "published_at"      => $item['snippet']['publishedAt'] ?? '',
    "country"           => $item['snippet']['country'] ?? '',
    "thumbnails"        => $item['snippet']['thumbnails'] ?? [],
    "banner"            => $item['brandingSettings']['image']['bannerExternalUrl'] ?? '',
    "subscriber_count"  => $item['statistics']['subscriberCount'] ?? '0',
    "view_count"        => $item['statistics']['viewCount'] ?? '0',
    "video_count"       => $item['statistics']['videoCount'] ?? '0',
    "uploads_playlist"  => $item['contentDetails']['relatedPlaylists']['uploads'] ?? '',
    "made_for_kids"     => $item['status']['madeForKids'] ?? false,
    "topic_categories"  => $item['topicDetails']['topicCategories'] ?? []
];

echo json_encode($result, JSON_PRETTY_PRINT);
