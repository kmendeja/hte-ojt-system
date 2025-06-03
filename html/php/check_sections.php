<?php
require_once 'db_connect.php';
header('Content-Type: application/json');

try {
    $query = "SELECT DISTINCT section, program FROM trainees ORDER BY program, section";
    $result = $conn->query($query);

    if (!$result) {
        throw new Exception("Query failed: " . $conn->error);
    }

    $sections = [];
    while ($row = $result->fetch_assoc()) {
        $sections[] = $row;
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