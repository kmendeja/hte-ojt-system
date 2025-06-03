<?php
session_start();
require_once 'db_connect.php';
header('Content-Type: application/json');

// Check if user is logged in and is trainee
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'trainee') {
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

    if (!isset($data['notification_id']) || !isset($data['notification_type'])) {
        throw new Exception('Notification ID and type are required');
    }

    $notification_id = intval($data['notification_id']);
    $notification_type = $data['notification_type'];
    $user_id = $_SESSION['user_id'];

    if ($notification_type === 'notification') {
        // Mark as read in notifications table
        $update_stmt = $conn->prepare("
            UPDATE notifications
            SET is_read = 1
            WHERE id = ? AND user_id = ?
        ");
        if (!$update_stmt) {
            throw new Exception("Update statement prepare failed: " . $conn->error);
        }
        $update_stmt->bind_param("ii", $notification_id, $user_id);
        if (!$update_stmt->execute()) {
            throw new Exception("Update query execute failed: " . $update_stmt->error);
        }
        if ($update_stmt->affected_rows === 0) {
            echo json_encode([
                'success' => false,
                'message' => 'Notification not found or you do not have permission to delete it'
            ]);
            exit;
        }
    } else if ($notification_type === 'admin_announcement' || $notification_type === 'coordinator_announcement') {
        // Insert a new notifications row for this user and announcement, mark as read
        $message = $notification_type === 'admin_announcement'
            ? 'announcement_' . $notification_id
            : 'announcement_' . $notification_id;
        $link = $notification_type === 'admin_announcement'
            ? '/trainee_notification.html?announcement_id=' . $notification_id
            : '/trainee_notification.html?announcement_id=' . $notification_id;
        $insert_stmt = $conn->prepare("
            INSERT INTO notifications (user_id, user_role, message, link, is_read, created_at)
            VALUES (?, 'trainee', ?, ?, 1, NOW())
            ON DUPLICATE KEY UPDATE is_read = 1
        ");
        if (!$insert_stmt) {
            throw new Exception("Insert statement prepare failed: " . $conn->error);
        }
        $insert_stmt->bind_param("iss", $user_id, $message, $link);
        if (!$insert_stmt->execute()) {
            throw new Exception("Insert query execute failed: " . $insert_stmt->error);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Unknown notification type.'
        ]);
        exit;
    }
    echo json_encode([
        'success' => true,
        'message' => 'Notification marked as read successfully'
    ]);

} catch (Exception $e) {
    error_log("Error in delete_trainee_notification.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to delete notification: ' . $e->getMessage()
    ]);
} finally {
    if (isset($update_stmt)) $update_stmt->close();
    if (isset($conn)) $conn->close();
}
?> 