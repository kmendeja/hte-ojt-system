<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

$user_id = $_SESSION['reset_user_id'] ?? null;
$otp = $_POST['otp'] ?? null;

if (!$user_id || !$otp) {
    echo json_encode(['success' => false, 'message' => 'Missing user or OTP.']);
    exit();
}

$sql = "UPDATE password_reset_tokens SET used = 1 WHERE user_id = ? AND token = ? AND used = 0";
$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $user_id, $otp);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'OTP not found or already expired.']);
} 