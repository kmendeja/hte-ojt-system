<?php
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

try {
    // Get JSON data from request body
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['id']) || !isset($data['title']) || !isset($data['content'])) {
        throw new Exception('Missing required fields');
    }
    
    // Start transaction
    $conn->autocommit(FALSE);
    
    // Verify ownership and update announcement
    $stmt = $conn->prepare("
        UPDATE coordinator_announcements 
        SET title = ?, 
            content = ?, 
            is_important = ?
        WHERE id = ? AND coordinator_id = ?
    ");
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("ssiii", 
        $data['title'],
        $data['content'],
        $data['is_important'],
        $data['id'],
        $_SESSION['user_id']
    );
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    if ($stmt->affected_rows === 0) {
        throw new Exception("No announcement found or you don't have permission to edit it");
    }
    
    // Delete existing recipients and section assignments
    $stmt = $conn->prepare("DELETE FROM coordinator_announcement_recipients WHERE announcement_id = ?");
    $stmt->bind_param("i", $data['id']);
    $stmt->execute();
    
    $stmt = $conn->prepare("DELETE FROM coordinator_announcement_sections WHERE announcement_id = ?");
    $stmt->bind_param("i", $data['id']);
    $stmt->execute();
    
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
    
    // Insert section assignments
    if (!empty($assignedSections)) {
        $stmt = $conn->prepare("INSERT INTO coordinator_announcement_sections (announcement_id, program, section) VALUES (?, ?, ?)");
        
        foreach ($assignedSections as $section) {
            $stmt->bind_param("iss", 
                $data['id'],
                $section['program'],
                $section['section']
            );
            if (!$stmt->execute()) {
                throw new Exception("Failed to assign announcement to section");
            }
        }
    }
    
    // Insert trainee recipients
    if (!empty($traineeIds)) {
        // Use a single INSERT ... SELECT to ensure only valid trainee_ids are inserted
        $inClause = implode(',', array_fill(0, count($traineeIds), '?'));
        $query = "INSERT INTO coordinator_announcement_recipients (announcement_id, trainee_id)
                  SELECT ?, trainee_id FROM trainees WHERE trainee_id IN ($inClause)";
        $stmt = $conn->prepare($query);
        // Build types string: 1 's' for announcement_id, then 's' for each trainee_id
        $types = str_repeat('s', count($traineeIds) + 1);
        $params = array_merge([$data['id']], $traineeIds);
        $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) {
            throw new Exception("Failed to assign announcement to trainees: " . $stmt->error);
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Announcement updated successfully'
    ]);

} catch (Exception $e) {
    // Rollback transaction
    $conn->rollback();
    
    error_log("Error updating announcement: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
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