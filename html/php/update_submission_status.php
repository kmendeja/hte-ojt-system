<?php
session_start();
require_once 'db_connect.php';
header('Content-Type: application/json');

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log incoming data
error_log("Received data: " . file_get_contents('php://input'));

// Check if user is logged in as coordinator
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'coordinator') {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized: Coordinator access required.'
    ]);
    exit;
}

// Get JSON data from request body
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request data'
    ]);
    exit;
}

// Log decoded data
error_log("Decoded data: " . print_r($data, true));

// Validate required fields
if (!isset($data['task_id']) || !isset($data['status']) || !isset($data['remarks'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required fields',
        'received_data' => $data
    ]);
    exit;
}

$task_id = $data['task_id'];
$status = $data['status'];
$remarks = $data['remarks'];

try {
    $conn->begin_transaction();

    // Update task submission status and mark as read
    $sql = "UPDATE task_submissions 
            SET status = ?, 
                remarks = ?,
                is_read = 1,
                date_updated = NOW() 
            WHERE id = ?";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $status, $remarks, $task_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to update submission status: " . $stmt->error);
    }

    if ($stmt->affected_rows === 0) {
        throw new Exception("No submission found with ID: " . $task_id);
    }

    // Get trainee info for notification
    $trainee_sql = "SELECT t.trainee_id, t.full_name, t.user_id, ts.submission_type 
                    FROM task_submissions ts
                    JOIN trainees t ON ts.trainee_id = t.trainee_id
                    WHERE ts.id = ?";
                    
    $trainee_stmt = $conn->prepare($trainee_sql);
    $trainee_stmt->bind_param("i", $task_id);
    $trainee_stmt->execute();
    $trainee_result = $trainee_stmt->get_result();
    $trainee = $trainee_result->fetch_assoc();

    if ($trainee) {
        // Send notification to trainee
        $notification_sql = "INSERT INTO notifications (user_id, user_role, message, link, created_at)
                           VALUES (?, 'trainee', ?, ?, NOW())";
                           
        $message = "Your {$trainee['submission_type']} has been {$status}";
        $link = "/trainee_submitTask.html";
        
        $notification_stmt = $conn->prepare($notification_sql);
        $notification_stmt->bind_param("iss", 
            $trainee['user_id'],
            $message,
            $link
        );
        
        if (!$notification_stmt->execute()) {
            throw new Exception("Failed to send notification: " . $notification_stmt->error);
        }
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Status updated successfully'
    ]);

} catch (Exception $e) {
    $conn->rollback();
    error_log("Error in update_submission_status.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error updating status: ' . $e->getMessage()
    ]);
} finally {
    if (isset($stmt)) $stmt->close();
    if (isset($trainee_stmt)) $trainee_stmt->close();
    if (isset($notification_stmt)) $notification_stmt->close();
    $conn->close();
}
?> 