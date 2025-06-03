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
    $notifications = [];
    
    // Debug log
    error_log("User ID: " . $_SESSION['user_id']);
    error_log("Role: " . $_SESSION['role']);
    
    // Get task submissions that haven't been read
    $stmt = $conn->prepare("
        SELECT 
            ts.id,
            ts.trainee_id,
            ts.submission_type,
            ts.comment,
            ts.status,
            ts.date_submitted,
            t.full_name as trainee_name,
            t.program,
            t.section
        FROM task_submissions ts
        INNER JOIN trainees t ON ts.trainee_id = t.trainee_id
        INNER JOIN section_assignments sa ON 
            t.program = sa.program AND 
            t.section = sa.section
        INNER JOIN coordinators c ON 
            sa.coordinator_id = c.coordinator_id
        WHERE c.user_id = ? 
        AND (ts.status IS NULL OR ts.status = 'pending')
        ORDER BY ts.date_submitted DESC
    ");
    
    if (!$stmt) {
        throw new Exception("Task submissions query prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $_SESSION['user_id']);
    
    if (!$stmt->execute()) {
        throw new Exception("Task submissions query execute failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    error_log("Task submissions found: " . $result->num_rows);
    
    while ($row = $result->fetch_assoc()) {
        $notifications[] = [
            'id' => $row['id'],
            'type' => 'requirement',
            'title' => 'New ' . $row['submission_type'],
            'content' => "Submission from {$row['trainee_name']} ({$row['program']}-{$row['section']})\n{$row['comment']}",
            'created_at' => $row['date_submitted'],
            'status' => $row['status'],
            'trainee_name' => $row['trainee_name'],
            'trainee_id' => $row['trainee_id']
        ];
    }
    
    // Get unread admin announcements
    $stmt = $conn->prepare("
        SELECT 
            'admin' as source,
            aa.id,
            aa.title,
            aa.content,
            aa.created_at,
            aa.is_important,
            u.username as created_by_name
        FROM admin_announcements aa
        LEFT JOIN users u ON aa.created_by = u.user_id
        LEFT JOIN notifications n ON 
            n.link LIKE CONCAT('%announcement_id=', aa.id)
            AND n.user_id = ?
            AND n.is_read = 1
        WHERE (aa.target_role = 'coordinators' OR aa.target_role = 'all')
        AND n.id IS NULL
        ORDER BY aa.created_at DESC
    ");
    
    if (!$stmt) {
        throw new Exception("Admin announcements query prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $_SESSION['user_id']);
    
    if (!$stmt->execute()) {
        throw new Exception("Admin announcements query execute failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    error_log("Admin announcements found: " . $result->num_rows);
    
    while ($row = $result->fetch_assoc()) {
        $notifications[] = [
            'id' => $row['id'],
            'type' => 'announcement',
            'title' => $row['title'],
            'content' => $row['content'],
            'created_at' => $row['created_at'],
            'is_important' => $row['is_important'],
            'source' => $row['source'],
            'created_by_name' => $row['created_by_name']
        ];
    }

    // Sort all notifications by created_at
    usort($notifications, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    
    echo json_encode([
        'success' => true,
        'notifications' => $notifications
    ]);

} catch (Exception $e) {
    error_log("Detailed error in get_coordinator_notifications.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load notifications: ' . $e->getMessage()
    ]);
} finally {
    if (isset($stmt)) {
        $stmt->close();
    }
    if (isset($conn)) {
        $conn->close();
    }
}
?> 