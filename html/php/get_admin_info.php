<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

try {
    // Verify admin session
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        echo json_encode([
            "success" => false,
            "message" => "Unauthorized: Admin access required."
        ]);
        exit;
    }
    
    $admin_id = $_SESSION['user_id'];

    // Get admin info from admins table
    $stmt = $conn->prepare("
    SELECT u.user_id, u.username, u.role, 
           a.full_name, a.position, a.email, a.contact_number, a.profile_image 
    FROM users u
    JOIN admins a ON u.user_id = a.user_id 
    WHERE u.user_id = ?
    ");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode([
            "success" => false,
            "message" => "Admin not found."
        ]);
        exit;
    }

    $profile = $result->fetch_assoc();
    echo json_encode([
        "success" => true,
        "profile" => $profile
    ]);

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}
?>