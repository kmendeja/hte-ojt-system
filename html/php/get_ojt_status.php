<?php
session_start();
require_once 'db_connect.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'trainee') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$trainee_id = $_SESSION['trainee_id'];

try {
    // Get trainee's OJT status
    $sql = "SELECT ojt_status FROM trainees WHERE trainee_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $trainee_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Trainee not found');
    }
    
    $trainee = $result->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'status' => $trainee['ojt_status'] ?? 'pending'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 