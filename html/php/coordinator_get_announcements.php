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

    // Get coordinator announcements based on role
    if ($userRole === 'coordinator') {
        // Get announcements created by this coordinator
        $query = "SELECT ca.*, u.username as coordinator_name,
                  GROUP_CONCAT(DISTINCT CONCAT(cas.program, '-', cas.section) SEPARATOR ', ') as target_sections
                  FROM coordinator_announcements ca 
                  LEFT JOIN users u ON ca.coordinator_id = u.user_id 
                  LEFT JOIN coordinator_announcement_sections cas ON ca.id = cas.announcement_id
                  WHERE ca.coordinator_id = ? 
                  GROUP BY ca.id
                  ORDER BY ca.created_at DESC";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $userId);
    } elseif ($userRole === 'trainee') {
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
        
        // Get announcements targeted to this trainee through section assignments or direct recipients
        $query = "SELECT DISTINCT ca.*, u.username as coordinator_name,
                  GROUP_CONCAT(DISTINCT CONCAT(cas.program, '-', cas.section) SEPARATOR ', ') as target_sections
                  FROM coordinator_announcements ca 
                  LEFT JOIN users u ON ca.coordinator_id = u.user_id 
                  LEFT JOIN coordinator_announcement_sections cas 
                      ON ca.id = cas.announcement_id 
                      AND cas.program = ? 
                      AND cas.section = ?
                  LEFT JOIN coordinator_announcement_recipients car 
                      ON ca.id = car.announcement_id 
                      AND car.trainee_id = ?
                  WHERE (cas.announcement_id IS NOT NULL OR car.announcement_id IS NOT NULL)
                  GROUP BY ca.id
                  ORDER BY ca.created_at DESC";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sss", 
            $traineeInfo['program'],
            $traineeInfo['section'],
            $traineeInfo['trainee_id']
        );
    } elseif ($userRole === 'admin') {
        // Admin can see all coordinator announcements
        $query = "SELECT ca.*, u.username as coordinator_name,
                  GROUP_CONCAT(DISTINCT CONCAT(cas.program, '-', cas.section) SEPARATOR ', ') as target_sections
                  FROM coordinator_announcements ca 
                  LEFT JOIN users u ON ca.coordinator_id = u.user_id 
                  LEFT JOIN coordinator_announcement_sections cas ON ca.id = cas.announcement_id
                  GROUP BY ca.id
                  ORDER BY ca.created_at DESC";
        
        $stmt = $conn->prepare($query);
    } else {
        throw new Exception("Invalid user role");
    }

    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        // For coordinators and admins, also fetch the list of recipients and sections
        if ($userRole === 'coordinator' || $userRole === 'admin') {
            // Get recipients
            $recipientStmt = $conn->prepare("
                SELECT t.trainee_id, t.full_name, t.program, t.section
                FROM coordinator_announcement_recipients car
                JOIN trainees t ON car.trainee_id = t.trainee_id
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
            $recipientStmt->close();
            
            // Get sections
            $sectionStmt = $conn->prepare("
                SELECT program, section
                FROM coordinator_announcement_sections
                WHERE announcement_id = ?
            ");
            $sectionStmt->bind_param("i", $row['id']);
            $sectionStmt->execute();
            $sectionResult = $sectionStmt->get_result();
            
            $sections = [];
            while ($section = $sectionResult->fetch_assoc()) {
                $sections[] = $section;
            }
            $row['sections'] = $sections;
            $sectionStmt->close();
        }
        
        $announcements[] = $row;
    }
    
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
    if (isset($conn)) {
        $conn->close();
    }
}
?> 