<?php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once  LIBRARY_PATH . 'csrf.php';

if (!isset($_SESSION['username'])) {
    http_response_code(401);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);

    // CSRF Protection
    if (!isset($_SERVER['HTTP_X_CSRF_TOKEN']) || $_SERVER['HTTP_X_CSRF_TOKEN'] !== $_SESSION['csrf_token']) {
        http_response_code(403);
        $csrf_token = generate_csrf_token();
        $respond = array(
            'success' => false,
            'csrf_token' => $respond
        );
        echo json_encode($callback);
        exit('CSRF token validation failed.');
    }

    // Input Validation
    if (!isset($data['id']) || !preg_match('/^\w+$/', $data['id'])) {
        // Id is either not set, or doesn't match the expected pattern (alphanumeric + underscore)
        http_response_code(400);
        exit('Invalid ID.');
    }

    $feedbackDB = RESOURCES_PATH . 'feedback/' . 'feedback_db.json';

    // Read existing JSON data from the feedback DB file
    $feedbackData = [];
    if (file_exists($feedbackDB)) {
        $jsonContent = file_get_contents($feedbackDB);
        if (!empty($jsonContent)) {
            $feedbackData = json_decode($jsonContent, true);
            // Check if the decoded data is an array
            if (!is_array($feedbackData)) {
                // Handle the case of invalid JSON data
                http_response_code(500);
                exit("Invalid JSON data in feedback_db.json");
            }
        }
    }

    // If $feedbackData is empty or not an array, set it as an empty array
    if (!is_array($feedbackData)) {
        $feedbackData = [];
    }

    // Find the feedback entry by ID
    $foundFeedback = null;
    foreach ($feedbackData as &$feedback) {
        if ($feedback['id'] === $data['id']) {
            $foundFeedback = &$feedback;
            break;
        }
    }

    if ($foundFeedback === null) {
        http_response_code(404);
        exit('Feedback entry not found.');
    }

    // Update the found feedback entry based on the action
    if ($data['action'] === 'upvote') {
        if (isset($foundFeedback["up"])) {
            $foundFeedback["up"]++;
        } else {
            $foundFeedback["up"] = 1;
        }
    } elseif ($data['action'] === 'downvote') {
        if (isset($foundFeedback["down"])) {
            $foundFeedback["down"]++;
        } else {
            $foundFeedback["down"] = 1;
        }
    }

    // Rewrite the feedback data back to the feedback DB file
    file_put_contents($feedbackDB, json_encode($feedbackData));

    $csrf_token = generate_csrf_token();
    $respond = array(
        'content' => $foundFeedback,
        'success' => true,
        'csrf_token' => $csrf_token
    );

    // Output JSON response
    echo json_encode($respond);
} else {
    http_response_code(405);
    exit('Method Not Allowed');
}
