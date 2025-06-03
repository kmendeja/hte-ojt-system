<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once 'db_connect.php';
header('Content-Type: application/json');

// Detailed session debugging
error_log("==================== START OF REQUEST ====================");
error_log("Session data: " . print_r($_SESSION, true));
error_log("User ID: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'not set'));
error_log("Role: " . (isset($_SESSION['role']) ? $_SESSION['role'] : 'not set'));

// Check if user is logged in and is trainee
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'trainee') {
    error_log("Authorization failed - User ID: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'not set') . ", Role: " . (isset($_SESSION['role']) ? $_SESSION['role'] : 'not set'));
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit;
}

try {
    $userRole = $_SESSION['role'];
    $userId = $_SESSION['user_id'];
    $notifications = [];

    // Get trainee information first
    $traineeInfo = null;
    if ($userRole === 'trainee') {
        $stmt = $conn->prepare("
            SELECT t.*, c.name as company_name
            FROM trainees t 
            LEFT JOIN companies c ON t.company_id = c.id
            WHERE t.user_id = ?
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $traineeInfo = $stmt->get_result()->fetch_assoc();
    }

    // Fetch all notifications for the current user
    $stmt = $conn->prepare("
        SELECT n.id, n.message, n.link, n.created_at, n.is_read
        FROM notifications n
        WHERE n.user_id = ? AND n.user_role = 'trainee'
        ORDER BY n.created_at DESC
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $notifications[] = [
            'id' => $row['id'], // This is the real notifications.id
            'title' => $row['message'],
            'content' => $row['message'], // Show the message as content/remarks
            'created_at' => $row['created_at'],
            'is_read' => $row['is_read'],
            // Add other fields as needed
        ];
    }

    if ($traineeInfo) {
        // Get coordinator announcements for this trainee through both section assignments and direct recipients
        $stmt = $conn->prepare("
            SELECT DISTINCT
                'coordinator' as source,
                ca.id,
                ca.title,
                ca.content,
                ca.created_at,
                ca.is_important,
                u.username as created_by_name,
                c.full_name as coordinator_info
            FROM coordinator_announcements ca
            INNER JOIN users u ON ca.coordinator_id = u.user_id
            INNER JOIN coordinators c ON u.user_id = c.user_id
            LEFT JOIN coordinator_announcement_sections cas 
                ON ca.id = cas.announcement_id 
                AND cas.program = ? 
                AND cas.section = ?
            LEFT JOIN coordinator_announcement_recipients car 
                ON ca.id = car.announcement_id 
                AND car.trainee_id = ?
            LEFT JOIN notifications n ON n.message = CONCAT('announcement_', ca.id)
                AND n.user_id = ? AND n.user_role = 'trainee' AND n.is_read = 1
            WHERE (cas.announcement_id IS NOT NULL OR car.announcement_id IS NOT NULL)
              AND n.id IS NULL
            ORDER BY ca.created_at DESC
        ");
        $stmt->bind_param("sssi", 
            $traineeInfo['program'],
            $traineeInfo['section'],
            $traineeInfo['trainee_id'],
            $userId
        );
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $notifications[] = [
                'id' => $row['id'],
                'type' => 'announcement',
                'title' => $row['title'],
                'content' => $row['content'],
                'created_at' => $row['created_at'],
                'is_important' => $row['is_important'],
                'source' => $row['source'],
                'created_by_name' => $row['coordinator_info']
            ];
        }
    }

    // Sort notifications by date (newest first)
    usort($notifications, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });

    echo json_encode([
        'success' => true,
        'notifications' => $notifications
    ]);

} catch (Exception $e) {
    error_log("Error fetching notifications: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch notifications'
    ]);
} finally {
    if (isset($stmt)) {
        $stmt->close();
    }
    if (isset($conn)) {
        $conn->close();
    }
    error_log("==================== END OF REQUEST ====================");
}
?> 