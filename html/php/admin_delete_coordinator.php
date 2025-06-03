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
    $conn->autocommit(FALSE);
    $stmt1 = $conn->prepare("DELETE FROM coordinators WHERE user_id=?");
    $stmt1->bind_param("i", $user_id);
    $stmt1->execute();
    $stmt2 = $conn->prepare("DELETE FROM users WHERE user_id=?");
    $stmt2->bind_param("i", $user_id);
    $stmt2->execute();
    $conn->commit();
    $conn->autocommit(TRUE);
    echo json_encode(['success' => true, 'message' => 'Coordinator permanently deleted.']);
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
        $conn->autocommit(TRUE);
    }
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
$conn->close();
?> 