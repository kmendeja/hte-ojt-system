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
    $program = $_GET['program'] ?? '';
    $assignment_id = isset($_GET['assignment_id']) ? intval($_GET['assignment_id']) : null;

    // 1. Get all possible sections for the program (from trainees table)
    $query = "SELECT DISTINCT section FROM trainees WHERE 1=1";
    $params = [];
    $types = "";
    if ($program) {
        $query .= " AND program = ?";
        $params[] = $program;
        $types .= "s";
    }
    $query .= " ORDER BY section";
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $all_sections = [];
    while ($row = $result->fetch_assoc()) {
        if ($row['section']) {
            $all_sections[] = $row['section'];
        }
    }
    // If no sections found, provide default sections
    if (empty($all_sections)) {
        $all_sections = ['4-A', '4-B', '4-C', '4-D'];
    }

    // 2. Get all assigned sections for this program (from section_assignments table)
    $assigned_sections = [];
    $assigned_query = "SELECT section FROM section_assignments WHERE program = ?";
    $assigned_params = [$program];
    $assigned_types = "s";
    if ($assignment_id) {
        // Exclude the current assignment's section from exclusion (for edit)
        $assigned_query .= " AND id != ?";
        $assigned_params[] = $assignment_id;
        $assigned_types .= "i";
    }
    $assigned_stmt = $conn->prepare($assigned_query);
    $assigned_stmt->bind_param($assigned_types, ...$assigned_params);
    $assigned_stmt->execute();
    $assigned_result = $assigned_stmt->get_result();
    while ($row = $assigned_result->fetch_assoc()) {
        if ($row['section']) {
            $assigned_sections[] = $row['section'];
        }
    }

    // 3. If editing, get the current section for this assignment
    $current_section = null;
    if ($assignment_id) {
        $current_stmt = $conn->prepare("SELECT section FROM section_assignments WHERE id = ?");
        $current_stmt->bind_param("i", $assignment_id);
        $current_stmt->execute();
        $current_result = $current_stmt->get_result();
        if ($row = $current_result->fetch_assoc()) {
            $current_section = $row['section'];
        }
    }

    // 4. Filter: only sections not assigned, plus the current one if editing
    $available_sections = [];
    foreach ($all_sections as $section) {
        if (!in_array($section, $assigned_sections) || ($current_section && $section == $current_section)) {
            $available_sections[] = $section;
        }
    }

    echo json_encode([
        'success' => true,
        'sections' => $available_sections
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?> 