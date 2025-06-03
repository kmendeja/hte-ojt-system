<?php
session_start();
require_once 'db_connect.php';
header('Content-Type: application/json');

// Only allow admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$search = $_GET['search'] ?? '';
$sort   = $_GET['sort'] ?? 'full_name';
$order  = strtoupper($_GET['order'] ?? 'ASC');
$allowedSort = ['full_name', 'employee_id', 'email', 'contact_number'];
if (!in_array($sort, $allowedSort)) $sort = 'full_name';
if (!in_array($order, ['ASC', 'DESC'])) $order = 'ASC';

$stmt = $conn->prepare("
    SELECT c.*, u.username
    FROM coordinators c
    JOIN users u ON c.user_id = u.user_id
    WHERE c.archived = 0
      AND c.full_name LIKE CONCAT('%', ?, '%')
    ORDER BY $sort $order
");
$stmt->bind_param("s", $search);
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
while ($row = $result->fetch_assoc()) {
    $row['password'] = '********'; // Never send real password
    $rows[] = $row;
}
echo json_encode(['success' => true, 'coordinators' => $rows]);
$conn->close();
?> 