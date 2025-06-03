<?php
session_start();
require_once 'db_connect.php';
header('Content-Type: application/json');

try {
    // Check if user is logged in as coordinator
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'coordinator') {
        throw new Exception('Unauthorized access');
    }

    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);
    if (!isset($data['trainee_id'])) {
        throw new Exception('Trainee ID is required');
    }

    $trainee_id = $data['trainee_id'];
    $coordinator_id = $_SESSION['user_id'];

    // Verify coordinator has access to this trainee's section
    $sql = "SELECT 1 FROM section_assignments sa 
            JOIN coordinators c ON sa.coordinator_id = c.coordinator_id
            JOIN trainees t ON t.program = sa.program AND t.section = sa.section
            WHERE c.user_id = ? AND t.trainee_id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $coordinator_id, $trainee_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('Unauthorized: You do not have access to delete this trainee');
    }

    // Start transaction
    $conn->begin_transaction();

    try {
        // Get user_id for this trainee
        $stmt = $conn->prepare("SELECT user_id FROM trainees WHERE trainee_id = ?");
        $stmt->bind_param("s", $trainee_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $trainee = $result->fetch_assoc();

        if (!$trainee) {
            throw new Exception('Trainee not found');
        }

        $user_id = $trainee['user_id'];

        // Delete from coordinator_trainees first
        $stmt = $conn->prepare("DELETE FROM coordinator_trainees WHERE trainee_id = ?");
        $stmt->bind_param("s", $trainee_id);
        $stmt->execute();

        // Delete from trainee_requirements
        $stmt = $conn->prepare("DELETE FROM trainee_requirements WHERE trainee_id = ?");
        $stmt->bind_param("s", $trainee_id);
        $stmt->execute();

        // Delete from task_submissions
        $stmt = $conn->prepare("DELETE FROM task_submissions WHERE trainee_id = ?");
        $stmt->bind_param("s", $trainee_id);
        $stmt->execute();

        // Delete from attendance_logs
        $stmt = $conn->prepare("DELETE FROM attendance_logs WHERE trainee_id = ?");
        $stmt->bind_param("s", $trainee_id);
        $stmt->execute();

        // Delete trainee record
        $stmt = $conn->prepare("DELETE FROM trainees WHERE trainee_id = ?");
        $stmt->bind_param("s", $trainee_id);
        $stmt->execute();

        // Delete user account
        $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();

        // Commit transaction
        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Trainee deleted successfully'
        ]);

    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        throw $e;
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?> 