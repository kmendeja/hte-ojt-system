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

if (!isset($data['id']) || !isset($data['title']) || !isset($data['content']) || !isset($data['target_role'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required fields'
    ]);
    exit;
}

try {
    // Start transaction
    $conn->autocommit(FALSE);
    
    // First check if announcement exists and get its current target role
    $stmt = $conn->prepare("SELECT target_role, title FROM admin_announcements WHERE id = ?");
    $stmt->bind_param("i", $data['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $currentAnnouncement = $result->fetch_assoc();
    
    if (!$currentAnnouncement) {
        echo json_encode([
            'success' => false,
            'message' => 'Announcement not found'
        ]);
        exit;
    }
    
    // Update the announcement
    $stmt = $conn->prepare("UPDATE admin_announcements 
                           SET title = ?, 
                               content = ?, 
                               target_role = ?, 
                               is_important = ?,
                               updated_at = CURRENT_TIMESTAMP
                           WHERE id = ?");
    
    $isImportant = isset($data['is_important']) && $data['is_important'] ? 1 : 0;
    $stmt->bind_param("sssii", 
        $data['title'],
        $data['content'],
        $data['target_role'],
        $isImportant,
        $data['id']
    );
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to update announcement: " . $stmt->error);
    }

    // Delete old notifications for this announcement
    $stmt = $conn->prepare("DELETE FROM notifications WHERE message LIKE CONCAT('%', ?, '%')");
    $stmt->bind_param("s", $currentAnnouncement['title']);
    $stmt->execute();

    // Add new notifications for the target audience
    if ($data['target_role'] === 'coordinators' || $data['target_role'] === 'all') {
        // Add notifications for coordinators
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, user_role, message, link, created_at) 
                               SELECT user_id, 'coordinator', ?, '/coordinator_notification.html', NOW()
                               FROM users WHERE role = 'coordinator'");
        $message = "Updated announcement: " . $data['title'];
        $stmt->bind_param("s", $message);
        $stmt->execute();
    }

    if ($data['target_role'] === 'trainees' || $data['target_role'] === 'all') {
        // Add notifications for trainees
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, user_role, message, link, created_at) 
                               SELECT user_id, 'trainee', ?, '/trainee_notification.html', NOW()
                               FROM users WHERE role = 'trainee'");
        $message = "Updated announcement: " . $data['title'];
        $stmt->bind_param("s", $message);
        $stmt->execute();
    }
    
    // Fetch the updated announcement with creator name
    $stmt = $conn->prepare("SELECT a.*, u.username as created_by_name 
                           FROM admin_announcements a 
                           LEFT JOIN users u ON a.created_by = u.user_id 
                           WHERE a.id = ?");
    $stmt->bind_param("i", $data['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $announcement = $result->fetch_assoc();
    
    if (!$announcement) {
        throw new Exception("Failed to fetch updated announcement");
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Announcement updated successfully',
        'announcement' => $announcement
    ]);
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    error_log("Error updating announcement: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to update announcement: ' . $e->getMessage()
    ]);
} finally {
    $conn->autocommit(TRUE);
    if (isset($stmt)) {
        $stmt->close();
    }
}
?> 