<?php
session_start();
header('Content-Type: application/json');

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log session data
error_log("Session check - Session data: " . print_r($_SESSION, true));

$response = [
    'isLoggedIn' => isset($_SESSION['user_id']),
    'role' => $_SESSION['role'] ?? null,
    'userId' => $_SESSION['user_id'] ?? null
];

// Log response
error_log("Session check - Response: " . print_r($response, true));

echo json_encode($response);
?> 