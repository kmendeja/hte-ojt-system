<?php
session_start();
require_once 'db_connect.php';
header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit;
}

// Get POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid JSON data received'
    ]);
    exit;
}

if (!isset($data['id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing announcement ID'
    ]);
    exit;
}

try {
    // Start transaction
    $conn->autocommit(FALSE);
    
    // Check if announcement exists
    $stmt = $conn->prepare("SELECT id FROM admin_announcements WHERE id = ?");
    $stmt->bind_param("i", $data['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Announcement not found'
        ]);
        exit;
    }
    
    // Delete the announcement - admin can delete any announcement
    $stmt = $conn->prepare("DELETE FROM admin_announcements WHERE id = ?");
    $stmt->bind_param("i", $data['id']);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to delete announcement");
    }

    // Delete related notifications
    $stmt = $conn->prepare("DELETE FROM notifications WHERE message LIKE CONCAT('%', (SELECT title FROM admin_announcements WHERE id = ?), '%')");
    $stmt->bind_param("i", $data['id']);
    $stmt->execute();
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Announcement deleted successfully'
    ]);
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    error_log("Error deleting announcement: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to delete announcement. Please try again.'
    ]);
} finally {
    $conn->autocommit(TRUE);
    if (isset($stmt)) {
        $stmt->close();
    }
}
?> 