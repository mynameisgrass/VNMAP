<?php
// tts.php - Supports VI, EN, JA, FR, CN
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// 1. CONFIGURATION
$apiKey = "AIzaSyCcIGWUDmKD54Swm73hNClWhSUnb8teEKY"; 
$url = "https://texttospeech.googleapis.com/v1/text:synthesize?key=" . $apiKey;

// 2. RECEIVE INPUT
$input = json_decode(file_get_contents('php://input'), true);
$text = $input['text'] ?? '';
$langCode = $input['lang'] ?? 'vi';

if (empty($text)) {
    http_response_code(400);
    die("No text provided");
}

// 3. SELECT VOICE CONFIG
$voiceConfig = [
    'vi' => ['code' => 'vi-VN', 'name' => 'vi-VN-Neural2-A'],
    'en' => ['code' => 'en-US', 'name' => 'en-US-Neural2-F'],
    'ja' => ['code' => 'ja-JP', 'name' => 'ja-JP-Neural2-B'],
    'fr' => ['code' => 'fr-FR', 'name' => 'fr-FR-Neural2-A'],
    'cn' => ['code' => 'cmn-CN', 'name' => 'cmn-CN-Wavenet-A'] 
];

$selected = $voiceConfig[$langCode] ?? $voiceConfig['vi'];

// 4. PREPARE JSON FOR GOOGLE
// Limit strictly to 4500 chars to avoid 5000 limit error
$safeText = mb_substr($text, 0, 4500);

$data = [
    'input' => ['text' => $safeText], 
    'voice' => [
        'languageCode' => $selected['code'],
        'name' => $selected['name'],
        'ssmlGender' => 'FEMALE'
    ],
    'audioConfig' => [
        'audioEncoding' => 'MP3',
        'speakingRate' => 1.0,
        'pitch' => 0.0
    ]
];

// 5. CURL REQUEST
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// 6. OUTPUT
if ($httpCode !== 200) {
    http_response_code(500);
    echo $response; // Return Google error for debugging
    exit;
}

$json = json_decode($response, true);
if (isset($json['audioContent'])) {
    $audioContent = base64_decode($json['audioContent']);
    header('Content-Type: audio/mpeg');
    echo $audioContent;
} else {
    http_response_code(500);
    echo "Invalid response from Google";
}
?>