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

// Get JSON data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid JSON data received'
    ]);
    exit;
}

if (!isset($data['title']) || !isset($data['content']) || !isset($data['target_role'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required fields'
    ]);
    exit;
}

// Validate target role
$validRoles = ['all', 'coordinators', 'trainees'];
if (!in_array(strtolower($data['target_role']), $validRoles)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid target role'
    ]);
    exit;
}

try {
    // Start transaction
    $conn->autocommit(FALSE);
    
    // Insert announcement
    $stmt = $conn->prepare("INSERT INTO admin_announcements (title, content, target_role, created_by, is_important, created_at) 
                           VALUES (?, ?, ?, ?, ?, NOW())");
    
    $isImportant = isset($data['is_important']) ? $data['is_important'] : 0;
    $stmt->bind_param("sssii", 
        $data['title'],
        $data['content'],
        $data['target_role'],
        $_SESSION['user_id'],
        $isImportant
    );
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to insert announcement");
    }
    
    $newId = $conn->insert_id;

    // Add notifications for the target audience
    if ($data['target_role'] === 'coordinators' || $data['target_role'] === 'all') {
        // Add notifications for coordinators
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, user_role, message, link, created_at) 
                               SELECT user_id, 'coordinator', ?, '/coordinator_notification.html', NOW()
                               FROM users WHERE role = 'coordinator'");
        $message = "New announcement: " . $data['title'];
        $stmt->bind_param("s", $message);
        $stmt->execute();
    }

    if ($data['target_role'] === 'trainees' || $data['target_role'] === 'all') {
        // Add notifications for trainees
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, user_role, message, link, created_at) 
                               SELECT user_id, 'trainee', ?, '/trainee_notification.html', NOW()
                               FROM users WHERE role = 'trainee'");
        $message = "New announcement: " . $data['title'];
        $stmt->bind_param("s", $message);
        $stmt->execute();
    }
    
    // Fetch the newly created announcement with creator name
    $stmt = $conn->prepare("SELECT a.*, u.username as created_by_name 
                           FROM admin_announcements a 
                           LEFT JOIN users u ON a.created_by = u.user_id 
                           WHERE a.id = ?");
    $stmt->bind_param("i", $newId);
    $stmt->execute();
    $result = $stmt->get_result();
    $announcement = $result->fetch_assoc();
    
    if (!$announcement) {
        throw new Exception("Failed to fetch created announcement");
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Announcement created successfully',
        'announcement' => $announcement
    ]);
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    error_log("Error creating announcement: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to create announcement. Please try again.'
    ]);
} finally {
    $conn->autocommit(TRUE);
    if (isset($stmt)) {
        $stmt->close();
    }
}
?> 