<?php
require_once 'db_connect.php';
header('Content-Type: application/json');

try {
    // Check database connection
    if (!isset($conn)) {
        throw new Exception("Database connection not established");
    }

    // Get all sections and programs from the database
    $sectionQuery = "SELECT DISTINCT program, section 
                    FROM section_assignments 
                    WHERE section IS NOT NULL 
                    ORDER BY program, section";
    $sectionResult = $conn->query($sectionQuery);
    
    if (!$sectionResult) {
        throw new Exception("Error fetching sections: " . $conn->error);
    }

    $stats = [];
    
    while ($row = $sectionResult->fetch_assoc()) {
        $program = $row['program'];
        $section = $row['section'];
        
        // Get submission stats for this program-section
        $query = "SELECT 
            COUNT(DISTINCT ts.id) as submitted_tasks,
            (SELECT COUNT(*) FROM task_submissions 
             WHERE submission_type IN ('Weekly Report', 'Submission of Deliverables')) as total_tasks
        FROM trainees t
        JOIN section_assignments sa ON 
            t.program = sa.program AND 
            t.section = sa.section
        LEFT JOIN task_submissions ts ON 
            t.trainee_id = ts.trainee_id AND
            ts.submission_type IN ('Weekly Report', 'Submission of Deliverables')
        WHERE sa.program = ? AND sa.section = ?
        GROUP BY sa.program, sa.section";
        
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("ss", $program, $section);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        
        $total_tasks = $data['total_tasks'] ?? 0;
        $submitted_tasks = $data['submitted_tasks'] ?? 0;
        
        // Calculate percentage
        $percentage = $total_tasks > 0 ? round(($submitted_tasks / $total_tasks) * 100) : 0;
        $stats[] = [
            'program' => $program,
            'section' => $section,
            'percent' => $percentage
        ];
        
        $stmt->close();
    }

    echo json_encode([
        'success' => true,
        'stats' => $stats
    ]);

} catch (Exception $e) {
    error_log("Error in get_task_stats.php: " . $e->getMessage());
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