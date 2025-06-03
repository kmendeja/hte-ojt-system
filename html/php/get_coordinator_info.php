<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'coordinator') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    $stmt = $conn->prepare("
        SELECT 
            c.coordinator_id,
            c.full_name,
            c.employee_id,
            c.job_description,
            c.email,
            c.contact_number,
            c.profile_picture
        FROM coordinators c
        WHERE c.user_id = ?
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $coordinator = $result->fetch_assoc();
        
        // Format the data for response
        $response = [
            'success' => true,
            'profile' => [
                'full_name' => $coordinator['full_name'],
                'employee_id' => $coordinator['employee_id'],
                'job_description' => $coordinator['job_description'],
                'email' => $coordinator['email'],
                'contact_number' => $coordinator['contact_number'],
                'profile_image' => $coordinator['profile_picture']
            ]
        ];
        
        echo json_encode($response);
    } else {
        echo json_encode(['success' => false, 'message' => 'Coordinator not found']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>