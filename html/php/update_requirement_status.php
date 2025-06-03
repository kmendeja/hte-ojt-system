<?php
session_start();
require_once 'db_connect.php';
header('Content-Type: application/json');

// Check if user is logged in as coordinator
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'coordinator') {
    die(json_encode([
        'success' => false,
        'message' => 'Unauthorized: Coordinator access required'
    ]));
}

try {
    // Get JSON data from request body
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['requirement_id']) || !isset($data['status'])) {
        throw new Exception('Missing required fields');
    }
    
    $requirement_id = $data['requirement_id'];
    $status = $data['status'];
    $remarks = $data['remarks'] ?? null;
    
    // Validate status
    if (!in_array($status, ['Pending', 'Approved', 'Rejected'])) {
        throw new Exception('Invalid status value');
    }
    
    // Update the requirement status
    $sql = "UPDATE trainee_requirements 
            SET status = ?, 
                remarks = ?,
                date_updated = CURRENT_TIMESTAMP
            WHERE id = ?";
            
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("ssi", $status, $remarks, $requirement_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    if ($stmt->affected_rows === 0) {
        throw new Exception("No requirement found with ID: " . $requirement_id);
    }
    
    // Get the trainee information for notification
    $sql = "SELECT tr.trainee_id, tr.requirement_name, t.user_id 
            FROM trainee_requirements tr
            INNER JOIN trainees t ON tr.trainee_id = t.trainee_id
            WHERE tr.id = ?";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $requirement_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $requirement = $result->fetch_assoc();
    
    // Insert notification for trainee
    $message = "Your requirement '{$requirement['requirement_name']}' has been {$status}";
    if ($remarks) {
        $message .= ". Remarks: {$remarks}";
    }
    
    $sql = "INSERT INTO notifications (user_id, user_role, message, link) 
            VALUES (?, 'trainee', ?, '/trainee_requirements.html')";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $requirement['user_id'], $message);
    $stmt->execute();
    
    echo json_encode([
        'success' => true,
        'message' => 'Requirement status updated successfully'
    ]);

} catch (Exception $e) {
    error_log("Error in update_requirement_status.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    if (isset($stmt)) $stmt->close();
    if (isset($conn)) $conn->close();
}
?> 