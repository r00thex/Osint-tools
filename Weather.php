<?php
error_reporting(0);
header("Content-Type: application/json; charset=UTF-8");


$city = isset($_GET['city']) ? $_GET['city'] : 'Dhaka';


$url = "https://wttr.in/{$city}?format=%C|%t|%h|%w|%P|%m|%S|%s|%f|%u|%v|%d|%l|%L|%z";
$response = file_get_contents($url);

if ($response) {

    list($weather, $temperature, $humidity, $wind, $pressure, $moon, $sunrise, $sunset, $feels_like, $uv_index, $visibility, $day_length, $latitude, $longitude, $timezone) = explode("|", $response);


    preg_match('/([NESW]+)\d+/', $wind, $wind_direction);
    $wind_dir = isset($wind_direction[1]) ? $wind_direction[1] : "N/A";


    $weather_info = [
        "🏙️ শহর" => ucfirst($city),
        "🌍 ভৌগোলিক অবস্থান" => trim("$latitude, $longitude", " ,"),
        "⏰ সময় অঞ্চল" => trim($timezone),
        "☁ আবহাওয়া" => $weather,
        "🌡 তাপমাত্রা" => str_replace(" ", "", $temperature),
        "🔥 অনুভূত তাপমাত্রা" => str_replace(" ", "", $feels_like),
        "💧 আর্দ্রতা" => str_replace(" ", "", $humidity),
        "🌬 বাতাসের গতি" => str_replace(["↘", "↙", "↗", "↖"], "", $wind),
        "🧭 বাতাসের দিক" => $wind_dir,
        "📊 বায়ুচাপ" => str_replace(" ", "", $pressure),
        "👀 দৃশ্যমানতা" => !empty($visibility) ? str_replace(" ", "", $visibility) : "N/A",
        "🌞 UV সূচক" => str_replace(" ", "", $uv_index),
        "🌙 চাঁদের দশা" => $moon,
        "🌅 সূর্যোদয়" => str_replace(" ", "", $sunrise),
        "🌇 সূর্যাস্ত" => str_replace(" ", "", $sunset),
        "⏳ দিনের দৈর্ঘ্য" => str_replace(" ", "", $day_length),
        "🥀 টেলিগ্রাম চ্যানেল" => "@",
        "🖍️ API মালিক" => "@"
    ];
} else {
    $weather_info = ["⚠️ ত্রুটি" => "শহরের তথ্য পাওয়া যায়নি!"];
}


echo json_encode($weather_info, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
?>
