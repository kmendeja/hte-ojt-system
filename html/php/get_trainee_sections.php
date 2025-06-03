<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

try {
    $program = isset($_GET['program']) ? $_GET['program'] : null;

    // Base query
    $query = "SELECT DISTINCT program, section FROM trainees";
    $params = [];
    $types = "";
    $where = [];

    // Add filters if provided
    if ($program) {
        $where[] = "program = ?";
        $params[] = $program;
        $types .= "s";
    }

    // Add WHERE clause if filters exist
    if (!empty($where)) {
        $query .= " WHERE " . implode(" AND ", $where);
    }

    $query .= " ORDER BY program, section";

    // Prepare and execute query
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    if (!$result) {
        throw new Exception("Query failed: " . $conn->error);
    }

    $sections = [];
    while ($row = $result->fetch_assoc()) {
        if (!isset($sections[$row['program']])) {
            $sections[$row['program']] = [];
        }
        if ($row['section']) {
            $sections[$row['program']][] = $row['section'];
        }
    }

    // If no sections found for a specific program, provide defaults
    if ($program && empty($sections[$program])) {
        $sections[$program] = ['4-A', '4-B', '4-C', '4-D'];
    }

    echo json_encode([
        'success' => true,
        'sections' => $sections
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

$conn->close();
?> 