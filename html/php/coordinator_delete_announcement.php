<?php
session_start();
require_once 'db_connect.php';
header('Content-Type: application/json');

// Check if user is logged in and is coordinator
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'coordinator') {
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
    
    // Verify the announcement belongs to this coordinator
    $stmt = $conn->prepare("SELECT id FROM coordinator_announcements WHERE id = ? AND coordinator_id = ?");
    $stmt->bind_param("ii", $data['id'], $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Announcement not found or unauthorized");
    }
    
    // Delete announcement recipients first (due to foreign key constraints)
    $stmt = $conn->prepare("DELETE FROM coordinator_announcement_recipients WHERE announcement_id = ?");
    $stmt->bind_param("i", $data['id']);
    if (!$stmt->execute()) {
        throw new Exception("Failed to delete announcement recipients");
    }
    
    // Delete the announcement
    $stmt = $conn->prepare("DELETE FROM coordinator_announcements WHERE id = ? AND coordinator_id = ?");
    $stmt->bind_param("ii", $data['id'], $_SESSION['user_id']);
    if (!$stmt->execute()) {
        throw new Exception("Failed to delete announcement");
    }
    
    if ($stmt->affected_rows === 0) {
        throw new Exception("Announcement not found or already deleted");
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Announcement deleted successfully'
    ]);
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    error_log("Error deleting coordinator announcement: " . $e->getMessage());
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