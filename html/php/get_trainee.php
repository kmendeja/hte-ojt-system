<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

try {
    // Check if user is logged in as coordinator
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'coordinator') {
        throw new Exception('Unauthorized access');
    }

    // Get trainee ID from query parameter
    if (!isset($_GET['id'])) {
        throw new Exception('Trainee ID is required');
    }

    $trainee_id = $_GET['id'];
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
        throw new Exception('Unauthorized: You do not have access to edit this trainee');
    }

    // Get trainee data
    $query = "SELECT * FROM trainees WHERE trainee_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $trainee_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('Trainee not found');
    }

    $trainee = $result->fetch_assoc();

    echo json_encode([
        "success" => true,
        "trainee" => $trainee
    ]);

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}
?> 