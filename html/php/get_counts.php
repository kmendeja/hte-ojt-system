<?php
header('Content-Type: application/json');

// Include database connection
require_once 'db_connect.php';

try {
    // Initialize counts with 0 as default
    $traineeCount = 0;
    $coordinatorCount = 0;
    $companyCount = 0;

    // Get trainee count
    $traineeQuery = "SELECT COUNT(*) as count FROM trainees";
    if ($traineeResult = $conn->query($traineeQuery)) {
        $traineeRow = $traineeResult->fetch_assoc();
        $traineeCount = $traineeRow['count'];
        $traineeResult->free();
    }

    // Get coordinator count - only count non-archived coordinators
    $coordinatorQuery = "SELECT COUNT(*) as count FROM coordinators WHERE archived = 0";
    if ($coordinatorResult = $conn->query($coordinatorQuery)) {
        $coordinatorRow = $coordinatorResult->fetch_assoc();
        $coordinatorCount = $coordinatorRow['count'];
        $coordinatorResult->free();
    }

    // Get company count
    $companyQuery = "SELECT COUNT(*) as count FROM companies";
    if ($companyResult = $conn->query($companyQuery)) {
        $companyRow = $companyResult->fetch_assoc();
        $companyCount = $companyRow['count'];
        $companyResult->free();
    }

    echo json_encode([
        'success' => true,
        'counts' => [
            'trainees' => $traineeCount,
            'coordinators' => $coordinatorCount,
            'companies' => $companyCount
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>