<?php
ob_start(); // Start output buffering
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

// Get JSON data from request body
$input = file_get_contents('php://input');
error_log("Received input: " . $input); // Log the raw input for debugging
$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("JSON decode error: " . json_last_error_msg()); // Log the specific JSON error
    echo json_encode([
        'success' => false,
        'message' => 'Invalid JSON data received: ' . json_last_error_msg()
    ]);
    exit;
}

if (!isset($data['title']) || !isset($data['content'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required fields'
    ]);
    exit;
}

try {
    // Start transaction
    $conn->autocommit(FALSE);
    
    // Get coordinator's assigned sections and their trainees
    $stmt = $conn->prepare("
        SELECT DISTINCT 
            t.trainee_id, 
            t.program, 
            t.section
        FROM trainees t
        INNER JOIN section_assignments sa ON 
            t.program = sa.program AND 
            t.section = sa.section
        INNER JOIN coordinators c ON 
            sa.coordinator_id = c.coordinator_id
        WHERE c.user_id = ?
    ");
    
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $traineeIds = [];
    $assignedSections = [];
    while ($row = $result->fetch_assoc()) {
        $traineeIds[] = $row['trainee_id'];
        // Store unique program-section combinations
        $sectionKey = $row['program'] . '-' . $row['section'];
        if (!isset($assignedSections[$sectionKey])) {
            $assignedSections[$sectionKey] = [
                'program' => $row['program'],
                'section' => $row['section']
            ];
        }
    }
    
    // Check if coordinator has any assigned sections
    if (empty($assignedSections)) {
        throw new Exception("You don't have any assigned sections to make announcements for");
    }
    
    // Check if coordinator has any trainees in their sections
    if (empty($traineeIds)) {
        throw new Exception("You don't have any trainees in your assigned sections");
    }
    
    // Insert announcement
    $stmt = $conn->prepare("INSERT INTO coordinator_announcements (title, content, coordinator_id, is_important, created_at) 
                           VALUES (?, ?, ?, ?, NOW())");
    
    $isImportant = isset($data['is_important']) ? 1 : 0;
    $stmt->bind_param("ssii", 
        $data['title'],
        $data['content'],
        $_SESSION['user_id'],
        $isImportant
    );
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to insert announcement: " . $stmt->error);
    }
    
    $announcementId = $conn->insert_id;
    
    // Insert section assignments for the announcement
    $stmt = $conn->prepare("INSERT INTO coordinator_announcement_sections (announcement_id, program, section) VALUES (?, ?, ?)");
    
    foreach ($assignedSections as $section) {
        $stmt->bind_param("iss", 
            $announcementId, 
            $section['program'], 
            $section['section']
        );
        if (!$stmt->execute()) {
            throw new Exception("Failed to assign announcement to section: " . $stmt->error);
        }
    }
    
    // Insert trainee recipients
    $stmt = $conn->prepare("INSERT INTO coordinator_announcement_recipients (announcement_id, trainee_id) VALUES (?, ?)");
    
    foreach ($traineeIds as $traineeId) {
        $stmt->bind_param("is", $announcementId, $traineeId);
        if (!$stmt->execute()) {
            throw new Exception("Failed to assign announcement to trainee: " . $stmt->error);
        }
    }
    
    // Commit transaction
    if (!$conn->commit()) {
        throw new Exception("Failed to commit transaction");
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Announcement created successfully'
    ]);

} catch (Exception $e) {
    // Rollback transaction
    $conn->rollback();
    
    error_log("Error creating announcement: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    // Restore autocommit mode
    $conn->autocommit(TRUE);
    
    if (isset($stmt)) {
        $stmt->close();
    }
    if (isset($conn)) {
        $conn->close();
    }
}
?> 