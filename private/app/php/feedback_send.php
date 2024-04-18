<?php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once  LIBRARY_PATH . 'csrf.php';

// Check if the user is logged in, if not return 401 Unauthorized
if (!isset($_SESSION['username'])) {
    http_response_code(401);
    exit;
}

// Function to sanitize input data
function sanitizeInput($data) {
    // Remove leading/trailing whitespace
    $data = trim($data);
    // Convert special characters to HTML entities
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the JSON string from the request body
    $jsonString = file_get_contents("php://input");
    
    // Decode the JSON string into an array
    $jsonData = json_decode($jsonString, true);
    error_log($jsonString);
    // CSRF Protection
	if (!isset($_SERVER['HTTP_X_CSRF_TOKEN']) || $_SERVER['HTTP_X_CSRF_TOKEN'] !== $_SESSION['csrf_token']) {
        http_response_code(403);

        $csrf_token = generate_csrf_token();
        $respond = array(
            'success' => false,
            'csrf_token' => $csrf_token
        );
        echo json_encode($respond);
        // exit('CSRF token validation failed.');
        exit;
    }

    // Check if decoding was successful and if the JSON is valid
    if ($jsonData === null && json_last_error() !== JSON_ERROR_NONE) {
        // If decoding fails or JSON is invalid, return 400 Bad Request
        echo('invalid data');
        http_response_code(400);
        exit;
    }


    if (isset($jsonData['message']) && $jsonData['message'] !== null){
        $message = $jsonData['message'];
    }

    // Extract 'content' and 'role' fields from the message
    $content = sanitizeInput($message['content']);
    $role = sanitizeInput($message['role']);
    
    // Check if content or role is empty after sanitization
    if(empty($content) || empty($role)) {
        // If content or role is empty, return 400 Bad Request
        error_log('invalid data');
        http_response_code(400);
        exit;
    }

    // Generate a unique identifier for the feedback file
    $uniqid = time() . uniqid();
    
    // Create an array with 'role' and 'content' fields
    $feedbackData = array(
        'id' => $uniqid,
        'role' => $role,
        'content' => $content
    );

    $dir = RESOURCES_PATH . 'feedback/';
    $feedbackDB = $dir . 'feedback_db.json';

    $currentFeedbackData = [];
    if (file_exists($feedbackDB)) {
        $currentFeedbackData = json_decode(file_get_contents($feedbackDB), true);
    }
    $currentFeedbackData[] = $feedbackData;

    // Convert array to JSON string
    $jsonFeedbackData = json_encode($currentFeedbackData);
    
    // Write the JSON string to a new feedback file
    file_put_contents($feedbackDB, $jsonFeedbackData);
    

    $csrf_token = generate_csrf_token();
    $response = array(
        'success' => true,
        'csrf_token' => $csrf_token
    );
    
    // Return the JSON string
    echo json_encode($response);
}
?>
