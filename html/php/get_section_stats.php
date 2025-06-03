<?php
require_once 'db_connect.php';
header('Content-Type: application/json');

try {
    // Check database connection
    if (!isset($conn)) {
        throw new Exception("Database connection not established");
    }

    // Get trainee counts per program-section
    $query = "SELECT sa.program, sa.section, COUNT(DISTINCT t.trainee_id) as trainee_count
             FROM section_assignments sa
             JOIN trainees t ON 
                 t.program = sa.program AND 
                 t.section = sa.section
             GROUP BY sa.program, sa.section
             ORDER BY sa.program, sa.section";
             
    $result = $conn->query($query);
    
    if (!$result) {
        throw new Exception("Error fetching trainee counts: " . $conn->error);
    }

    $stats = [];
    
    // Build stats array from results
    while ($row = $result->fetch_assoc()) {
        $stats[] = [
            'program' => $row['program'],
            'section' => $row['section'],
            'trainee_count' => (int)$row['trainee_count']
        ];
    }

    echo json_encode([
        'success' => true,
        'stats' => $stats
    ]);

} catch (Exception $e) {
    error_log("Error in get_section_stats.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?> 