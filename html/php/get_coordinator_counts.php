<?php
header('Content-Type: application/json');
require_once 'db_connect.php'; // Your database connection file

try {
    // Get trainee count
    $traineeQuery = "SELECT COUNT(*) as count FROM trainees";
    $traineeResult = $conn->query($traineeQuery);
    $traineeCount = $traineeResult->fetch_assoc()['count'];

    // Get company count
    $companyQuery = "SELECT COUNT(*) as count FROM companies";
    $companyResult = $conn->query($companyQuery);
    $companyCount = $companyResult->fetch_assoc()['count'];

    echo json_encode([
        'success' => true,
        'counts' => [
            'trainees' => $traineeCount,
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