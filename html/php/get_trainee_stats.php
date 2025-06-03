<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

try {
    // First, check if we can connect to the database
    if (!$conn) {
        throw new Exception("Database connection failed");
    }

    // Get trainee count by program
    $sql = "SELECT 
                COUNT(*) as count,
                program
            FROM trainees
            GROUP BY program
            ORDER BY program ASC";
    
    $result = $conn->query($sql);
    
    if (!$result) {
        throw new Exception("Database query failed: " . $conn->error);
    }
    
    $stats = array();
    while ($row = $result->fetch_assoc()) {
        // Add to stats with 'label' for chart.js compatibility
        $stats[] = array(
            'label' => $row['program'],
            'count' => (int)$row['count']
        );
    }
    
    // If no data found, create sample data for testing
    if (empty($stats)) {
        $stats[] = array(
            'label' => 'Sample Program',
            'count' => 0
        );
    }
    
    // Add debug information
    $debug = array(
        'query' => $sql,
        'raw_stats' => $stats,
        'connection_status' => !empty($conn),
        'total_records' => array_sum(array_column($stats, 'count'))
    );
    
    echo json_encode(array(
        'success' => true,
        'data' => $stats,
        'debug' => $debug
    ));
    
} catch (Exception $e) {
    echo json_encode(array(
        'success' => false,
        'message' => $e->getMessage(),
        'debug' => isset($debug) ? $debug : null
    ));
}

$conn->close();
?> 