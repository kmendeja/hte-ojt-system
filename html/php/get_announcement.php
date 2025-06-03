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

// Get announcement ID from query parameter
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid announcement ID'
    ]);
    exit;
}

try {
    $userRole = $_SESSION['role'];
    $userId = $_SESSION['user_id'];
    
    // First try to find in admin announcements
    $query = "SELECT a.*, u.username as created_by_name, 'admin' as announcement_type 
              FROM admin_announcements a 
              LEFT JOIN users u ON a.created_by = u.user_id 
              WHERE a.id = ? ";
    
    // Add role-based visibility conditions
    if ($userRole === 'trainee') {
        $query .= "AND (a.target_role = 'trainees' OR a.target_role = 'all') ";
    } elseif ($userRole === 'coordinator') {
        $query .= "AND (a.target_role = 'coordinators' OR a.target_role = 'all') ";
    }
    // Admin can see all announcements
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Found in admin announcements
        $announcement = $result->fetch_assoc();
        echo json_encode([
            'success' => true,
            'announcement' => $announcement
        ]);
        exit;
    }
    
    // If not found in admin announcements, try coordinator announcements
    if ($userRole === 'trainee') {
        // Get trainee info first
        $traineeStmt = $conn->prepare("
            SELECT program, section, trainee_id
            FROM trainees
            WHERE user_id = ?
        ");
        $traineeStmt->bind_param("i", $userId);
        $traineeStmt->execute();
        $traineeInfo = $traineeStmt->get_result()->fetch_assoc();
        
        if (!$traineeInfo) {
            throw new Exception("Trainee information not found");
        }
        
        $query = "SELECT ca.*, u.username as coordinator_name, 'coordinator' as announcement_type 
                  FROM coordinator_announcements ca 
                  LEFT JOIN users u ON ca.coordinator_id = u.user_id 
                  LEFT JOIN coordinator_announcement_sections cas 
                      ON ca.id = cas.announcement_id 
                      AND cas.program = ? 
                      AND cas.section = ?
                  LEFT JOIN coordinator_announcement_recipients car 
                      ON ca.id = car.announcement_id 
                      AND car.trainee_id = ?
                  WHERE ca.id = ? 
                  AND (cas.announcement_id IS NOT NULL OR car.announcement_id IS NOT NULL)";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sssii", 
            $traineeInfo['program'],
            $traineeInfo['section'],
            $traineeInfo['trainee_id'],
            $id
        );
    } elseif ($userRole === 'coordinator') {
        $query = "SELECT ca.*, u.username as coordinator_name, 'coordinator' as announcement_type 
                  FROM coordinator_announcements ca 
                  LEFT JOIN users u ON ca.coordinator_id = u.user_id 
                  WHERE ca.id = ? AND ca.coordinator_id = ?";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $id, $userId);
    } else {
        // Admin can see all coordinator announcements
        $query = "SELECT ca.*, u.username as coordinator_name, 'coordinator' as announcement_type 
                  FROM coordinator_announcements ca 
                  LEFT JOIN users u ON ca.coordinator_id = u.user_id 
                  WHERE ca.id = ?";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $id);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $announcement = $result->fetch_assoc();
        
        // For coordinators and admins, also fetch the list of recipients and sections
        if ($userRole === 'coordinator' || $userRole === 'admin') {
            // Get recipients
            $recipientStmt = $conn->prepare("
                SELECT t.trainee_id, t.full_name, t.program, t.section
                FROM coordinator_announcement_recipients car
                JOIN trainees t ON car.trainee_id = t.trainee_id
                WHERE car.announcement_id = ?
            ");
            $recipientStmt->bind_param("i", $id);
            $recipientStmt->execute();
            $recipientResult = $recipientStmt->get_result();
            
            $recipients = [];
            while ($recipient = $recipientResult->fetch_assoc()) {
                $recipients[] = $recipient;
            }
            $announcement['recipients'] = $recipients;
            $recipientStmt->close();
            
            // Get sections
            $sectionStmt = $conn->prepare("
                SELECT program, section
                FROM coordinator_announcement_sections
                WHERE announcement_id = ?
            ");
            $sectionStmt->bind_param("i", $id);
            $sectionStmt->execute();
            $sectionResult = $sectionStmt->get_result();
            
            $sections = [];
            while ($section = $sectionResult->fetch_assoc()) {
                $sections[] = $section;
            }
            $announcement['sections'] = $sections;
            $sectionStmt->close();
        }
        
        echo json_encode([
            'success' => true,
            'announcement' => $announcement
        ]);
        exit;
    }
    
    // If we get here, announcement was not found or user doesn't have access
    echo json_encode([
        'success' => false,
        'message' => 'Announcement not found or access denied'
    ]);

} catch (Exception $e) {
    error_log("Error fetching announcement: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch announcement. Please try again.'
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