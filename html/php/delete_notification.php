<?php
session_start();
require_once 'db_connect.php';
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in and is coordinator
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'coordinator') {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit;
}

try {
    // Get notification ID from POST request
    $json_data = file_get_contents('php://input');
    $data = json_decode($json_data, true);

    if (!isset($data['notification_id'])) {
        throw new Exception('Notification ID is required');
    }

    $notification_id = intval($data['notification_id']);

    // First check if this is a task submission notification
    $check_task_stmt = $conn->prepare("
        SELECT ts.id 
        FROM task_submissions ts
        INNER JOIN trainees t ON ts.trainee_id = t.trainee_id
        INNER JOIN section_assignments sa ON 
            t.program = sa.program AND 
            t.section = sa.section
        INNER JOIN coordinators c ON 
            sa.coordinator_id = c.coordinator_id
        WHERE ts.id = ? AND c.user_id = ?
    ");

    if (!$check_task_stmt) {
        throw new Exception("Task check query prepare failed: " . $conn->error);
    }

    $check_task_stmt->bind_param("ii", $notification_id, $_SESSION['user_id']);
    
    if (!$check_task_stmt->execute()) {
        throw new Exception("Task check query execute failed: " . $check_task_stmt->error);
    }

    $task_result = $check_task_stmt->get_result();

    // If not a task submission, check if it's an admin announcement
    $check_admin_stmt = $conn->prepare("
        SELECT aa.id 
        FROM admin_announcements aa
        WHERE aa.id = ? 
        AND (aa.target_role = 'coordinators' OR aa.target_role = 'all')
    ");

    if (!$check_admin_stmt) {
        throw new Exception("Admin check query prepare failed: " . $conn->error);
    }

    $check_admin_stmt->bind_param("i", $notification_id);
    
    if (!$check_admin_stmt->execute()) {
        throw new Exception("Admin check query execute failed: " . $check_admin_stmt->error);
    }

    $admin_result = $check_admin_stmt->get_result();

    // If notification not found or user doesn't have permission
    if ($task_result->num_rows === 0 && $admin_result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Notification not found or you do not have permission to delete it'
        ]);
        exit;
    }

    // If we get here, the notification exists and the coordinator has permission to delete it
    // Insert or update a record in the notifications table to mark it as read
    $update_stmt = $conn->prepare("
        INSERT INTO notifications (user_id, user_role, message, link, is_read, created_at)
        VALUES (?, 'coordinator', 
            CASE 
                WHEN ? IN (SELECT id FROM task_submissions) THEN CONCAT('task_submission_', ?)
                ELSE CONCAT('announcement_', ?)
            END,
            CASE 
                WHEN ? IN (SELECT id FROM task_submissions) THEN CONCAT('/coordinator_SubmissionOverviewHistory.html?id=', ?)
                ELSE CONCAT('/coordinator_notification.html?announcement_id=', ?)
            END,
            1, NOW())
        ON DUPLICATE KEY UPDATE is_read = 1
    ");

    if (!$update_stmt) {
        throw new Exception("Update statement prepare failed: " . $conn->error);
    }

    $update_stmt->bind_param("iiiiiii", 
        $_SESSION['user_id'], 
        $notification_id,
        $notification_id,
        $notification_id,
        $notification_id,
        $notification_id,
        $notification_id
    );

    if (!$update_stmt->execute()) {
        throw new Exception("Update query execute failed: " . $update_stmt->error);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Notification marked as read successfully'
    ]);

} catch (Exception $e) {
    error_log("Error in delete_notification.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to delete notification: ' . $e->getMessage()
    ]);
} finally {
    if (isset($check_task_stmt)) $check_task_stmt->close();
    if (isset($check_admin_stmt)) $check_admin_stmt->close();
    if (isset($update_stmt)) $update_stmt->close();
    if (isset($conn)) $conn->close();
}
?> 