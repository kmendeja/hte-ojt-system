<?php
session_start();
require_once 'db_connect.php';
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Not logged in'
    ]);
    exit;
}

try {
    $userRole = $_SESSION['role'];
    $userId = $_SESSION['user_id'];
    $announcements = [];
    
    // Get admin announcements based on user role
    $adminQuery = "SELECT a.*, u.username as created_by_name 
                  FROM admin_announcements a 
                  LEFT JOIN users u ON a.created_by = u.user_id 
                  WHERE 1=1 ";
    
    if ($userRole === 'trainee') {
        $adminQuery .= "AND (a.target_role = 'trainees' OR a.target_role = 'all') ";
    } elseif ($userRole === 'coordinator') {
        $adminQuery .= "AND (a.target_role = 'coordinators' OR a.target_role = 'all') ";
    }
    // Admin can see all announcements
    
    $adminQuery .= "ORDER BY a.created_at DESC";
    
    $stmt = $conn->prepare($adminQuery);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $row['announcement_type'] = 'admin';
        $announcements[] = $row;
    }
    
    // Get coordinator announcements if user is trainee or coordinator
    if ($userRole === 'trainee') {
        $coordQuery = "SELECT ca.*, u.username as coordinator_name 
                      FROM coordinator_announcements ca 
                      LEFT JOIN users u ON ca.coordinator_id = u.user_id 
                      INNER JOIN coordinator_announcement_recipients car ON ca.id = car.announcement_id 
                      WHERE car.trainee_id = ? 
                      ORDER BY ca.created_at DESC";
        
        $stmt = $conn->prepare($coordQuery);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $row['announcement_type'] = 'coordinator';
            $announcements[] = $row;
        }
    } elseif ($userRole === 'coordinator') {
        $coordQuery = "SELECT ca.*, u.username as coordinator_name 
                      FROM coordinator_announcements ca 
                      LEFT JOIN users u ON ca.coordinator_id = u.user_id 
                      WHERE ca.coordinator_id = ? 
                      ORDER BY ca.created_at DESC";
        
        $stmt = $conn->prepare($coordQuery);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            // Get recipients for each announcement
            $recipientStmt = $conn->prepare("
                SELECT u.user_id, u.username 
                FROM coordinator_announcement_recipients car
                JOIN users u ON car.trainee_id = u.user_id
                WHERE car.announcement_id = ?
            ");
            $recipientStmt->bind_param("i", $row['id']);
            $recipientStmt->execute();
            $recipientResult = $recipientStmt->get_result();
            
            $recipients = [];
            while ($recipient = $recipientResult->fetch_assoc()) {
                $recipients[] = $recipient;
            }
            $row['recipients'] = $recipients;
            $row['announcement_type'] = 'coordinator';
            $announcements[] = $row;
            $recipientStmt->close();
        }
    }
    
    // Sort all announcements by created_at
    usort($announcements, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    
    echo json_encode([
        'success' => true,
        'announcements' => $announcements
    ]);
} catch (Exception $e) {
    error_log("Error fetching announcements: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch announcements. Please try again.'
    ]);
} finally {
    if (isset($stmt)) {
        $stmt->close();
    }
}
?>