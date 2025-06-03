<?php
session_start();
require_once 'db_connect.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$stmt = $conn->prepare("
    SELECT c.*, u.username
    FROM coordinators c
    JOIN users u ON c.user_id = u.user_id
    WHERE c.archived = 1
");
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
}
echo json_encode(['success' => true, 'coordinators' => $rows]);
$conn->close();
?> 