<?php
session_start();
require_once 'db_connect.php';
header('Content-Type: application/json');

try {
    // Check if user is logged in as coordinator
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'coordinator') {
        throw new Exception('Unauthorized access');
    }

    $coordinator_id = $_SESSION['user_id'];

    // Get sections assigned to this coordinator
    $sql = "SELECT DISTINCT program, section
            FROM section_assignments sa
            JOIN coordinators c ON sa.coordinator_id = c.coordinator_id
            WHERE c.user_id = ?
            ORDER BY program, section";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $coordinator_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $assigned_sections = [];
    $programs = [];
    
    while ($row = $result->fetch_assoc()) {
        $assigned_sections[] = [
            'program' => $row['program'],
            'section' => $row['section']
        ];
        
        if (!in_array($row['program'], $programs)) {
            $programs[] = $row['program'];
        }
    }

    echo json_encode([
        'success' => true,
        'assigned_sections' => $assigned_sections,
        'programs' => $programs
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

$conn->close();
?> 