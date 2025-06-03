<?php
session_start();
require_once 'db_connect.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!isset($_POST['user_id']) || !is_numeric($_POST['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing or invalid user_id']);
    exit;
}

$user_id = intval($_POST['user_id']);

try {
    // Set archived=1 for the coordinator
    $stmt = $conn->prepare("UPDATE coordinators SET archived=1 WHERE user_id=?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Coordinator archived successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Coordinator not found or already archived.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
$conn->close();
?> 