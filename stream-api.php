<?php

session_start();
if (!isset($_SESSION['username'])) {
	http_response_code(401);
	exit;
}

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');

// Add this block to handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
	http_response_code(204);
	exit;
}

if (file_exists(".env")){
    $env = parse_ini_file('.env');
}

// Replace with your API URL and API key
$apiUrl = isset($env) ? $env['OPENAI_API_URL'] : getenv('OPENAI_API_URL');
$apiKey = isset($env) ? $env['OPENAI_API_KEY'] : getenv('OPENAI_API_KEY');

// Read the request payload from the client
$requestPayload = file_get_contents('php://input');
if (true == $env['FORCE_MODEL']) {
	$r = json_decode($requestPayload, true);
	$r['model'] = $env['GPT_MODEL'];
	$requestPayload  = json_encode($r);
}

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $requestPayload);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
	'Authorization: Bearer ' . $apiKey,  # necessary for OpenAI
	'api-key: ' . $apiKey,               # necessary for Microsoft Azure AI
	'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) {
	echo $data;
	ob_flush();
	flush();
	return strlen($data);
});

curl_exec($ch);

if (curl_errno($ch)) {
	echo 'Error:' . curl_error($ch);
}

curl_close($ch);

