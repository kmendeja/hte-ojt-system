<?php
session_start();
require_once 'db_connect.php';
header('Content-Type: application/json');

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

try {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    if ($action === 'list') {
        $result = $conn->query("SELECT sa.*, c.full_name, c.contact_number 
                               FROM section_assignments sa 
                               JOIN coordinators c ON sa.coordinator_id = c.coordinator_id
                               ORDER BY sa.program, sa.section");
        
        if (!$result) {
            throw new Exception("Failed to fetch assignments: " . $conn->error);
        }
        
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        echo json_encode(['success' => true, 'assignments' => $rows]);
        
    } elseif ($action === 'add') {
        if (!isset($_POST['coordinator_id'], $_POST['program'], $_POST['section'])) {
            throw new Exception("Missing required fields");
        }

        $coordinator_id = intval($_POST['coordinator_id']);
        $program = $_POST['program'];
        $section = $_POST['section'];

        // Validate coordinator exists
        $coord_check = $conn->prepare("SELECT coordinator_id FROM coordinators WHERE coordinator_id = ?");
        $coord_check->bind_param("i", $coordinator_id);
        $coord_check->execute();
        if ($coord_check->get_result()->num_rows === 0) {
            throw new Exception("Invalid coordinator selected");
        }

        // Check if this section is already assigned
        $check_existing = $conn->prepare("SELECT sa.*, c.full_name 
                                        FROM section_assignments sa 
                                        JOIN coordinators c ON sa.coordinator_id = c.coordinator_id 
                                        WHERE sa.program = ? AND sa.section = ?");
        $check_existing->bind_param("ss", $program, $section);
        $check_existing->execute();
        $existing_result = $check_existing->get_result();
        
        if ($existing_result->num_rows > 0) {
            $existing = $existing_result->fetch_assoc();
            throw new Exception("This section is already assigned to coordinator {$existing['full_name']}");
        }

        $stmt = $conn->prepare("INSERT INTO section_assignments (coordinator_id, program, section) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $coordinator_id, $program, $section);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to add section assignment: " . $stmt->error);
        }

        echo json_encode(['success' => true, 'message' => 'Section assignment added successfully']);
        
    } elseif ($action === 'edit') {
        if (!isset($_POST['id'], $_POST['coordinator_id'], $_POST['program'], $_POST['section'])) {
            throw new Exception("Missing required fields");
        }

        $id = intval($_POST['id']);
        $coordinator_id = intval($_POST['coordinator_id']);
        $program = $_POST['program'];
        $section = $_POST['section'];

        // Check if the assignment exists
        $current_check = $conn->prepare("SELECT * FROM section_assignments WHERE id = ?");
        $current_check->bind_param("i", $id);
        $current_check->execute();
        if ($current_check->get_result()->num_rows === 0) {
            throw new Exception("Assignment not found");
        }

        // Check if coordinator exists
        $coord_check = $conn->prepare("SELECT coordinator_id FROM coordinators WHERE coordinator_id = ?");
        $coord_check->bind_param("i", $coordinator_id);
        $coord_check->execute();
        if ($coord_check->get_result()->num_rows === 0) {
            throw new Exception("Invalid coordinator selected");
        }

        // Check if this section is already assigned to another coordinator
        $check_existing = $conn->prepare("SELECT sa.*, c.full_name 
                                        FROM section_assignments sa 
                                        JOIN coordinators c ON sa.coordinator_id = c.coordinator_id 
                                        WHERE sa.program = ? AND sa.section = ? AND sa.id != ?");
        $check_existing->bind_param("ssi", $program, $section, $id);
        $check_existing->execute();
        $existing_result = $check_existing->get_result();
        
        if ($existing_result->num_rows > 0) {
            $existing = $existing_result->fetch_assoc();
            throw new Exception("This section is already assigned to coordinator {$existing['full_name']}");
        }

        $stmt = $conn->prepare("UPDATE section_assignments SET coordinator_id=?, program=?, section=? WHERE id=?");
        $stmt->bind_param("issi", $coordinator_id, $program, $section, $id);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to update section assignment: " . $stmt->error);
        }

        echo json_encode(['success' => true, 'message' => 'Section assignment updated successfully']);
        
    } elseif ($action === 'delete') {
        if (!isset($_POST['id'])) {
            throw new Exception("Missing assignment ID");
        }

        $id = intval($_POST['id']);
        
        // Check if the assignment exists
        $check = $conn->prepare("SELECT * FROM section_assignments WHERE id = ?");
        $check->bind_param("i", $id);
        $check->execute();
        if ($check->get_result()->num_rows === 0) {
            throw new Exception("Assignment not found");
        }

        $stmt = $conn->prepare("DELETE FROM section_assignments WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to delete section assignment: " . $stmt->error);
        }

        echo json_encode(['success' => true, 'message' => 'Section assignment deleted successfully']);
        
    } else {
        throw new Exception("Invalid action");
    }

} catch (Exception $e) {
    error_log("Section Assignment Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>
