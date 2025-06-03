<?php
session_start();
require_once 'db_connect.php';
header('Content-Type: application/json');

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Debug log
error_log("Session data: " . print_r($_SESSION, true));

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    error_log("User not logged in");
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

// Check if role is set and is coordinator
if (!isset($_SESSION['role'])) {
    error_log("Role not set in session");
    echo json_encode(['success' => false, 'message' => 'Role not set']);
    exit;
}

if ($_SESSION['role'] !== 'coordinator') {
    error_log("Invalid role: " . $_SESSION['role']);
    echo json_encode(['success' => false, 'message' => 'Invalid role']);
    exit;
}

$user_id = $_SESSION['user_id'];
error_log("User ID: " . $user_id);

try {
    // First get the coordinator's ID from the coordinators table
    $sql = "SELECT coordinator_id FROM coordinators WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $coordinator = $result->fetch_assoc();
    
    if (!$coordinator) {
        error_log("Coordinator not found for user_id: " . $user_id);
        echo json_encode(['success' => false, 'message' => 'Coordinator not found in database']);
        exit;
    }

    error_log("Found coordinator_id: " . $coordinator['coordinator_id']);

    // Get all submissions for trainees in sections assigned to this coordinator
    $sql = "SELECT DISTINCT
                ts.id as submission_id,
                t.trainee_id,
                ts.submission_type,
                ts.comment,
                GROUP_CONCAT(tf.file_path) as file_paths,
                GROUP_CONCAT(tf.original_name) as file_names,
                ts.date_submitted,
                COALESCE(ts.status, 'Pending') as status,
                COALESCE(ts.remarks, '') as remarks,
                t.full_name,
                t.program,
                t.section,
                t.email,
                t.status as trainee_status
            FROM trainees t
            INNER JOIN section_assignments sa ON 
                sa.coordinator_id = ? AND
                sa.program = t.program AND 
                sa.section = t.section
            LEFT JOIN task_submissions ts ON t.trainee_id = ts.trainee_id
            LEFT JOIN task_files tf ON ts.id = tf.submission_id
            WHERE ts.submission_type IN ('Weekly Report', 'Submission of Deliverables')
              AND ts.is_read = 0
            GROUP BY 
                ts.id,
                t.trainee_id,
                ts.submission_type,
                ts.comment,
                ts.date_submitted,
                ts.status,
                ts.remarks,
                t.full_name,
                t.program,
                t.section,
                t.email,
                t.status
            ORDER BY 
                t.trainee_id, 
                ts.date_submitted DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $coordinator['coordinator_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $submissions = [];
    $count = 0;

    while ($row = $result->fetch_assoc()) {
        // Skip if essential data is missing
        if (empty($row['trainee_id'])) {
            continue;
        }
        
        // Format dates
        if (!empty($row['date_submitted'])) {
            $row['date_submitted'] = date('Y-m-d H:i:s', strtotime($row['date_submitted']));
        }

        // Process file paths and names into an array of files
        if ($row['file_paths']) {
            $paths = explode(',', $row['file_paths']);
            $names = explode(',', $row['file_names']);
            $row['files'] = array_map(function($path, $name) {
                return ['path' => $path, 'name' => $name];
            }, $paths, $names);
        } else {
            $row['files'] = [];
        }

        // Remove raw file paths and names from the row
        unset($row['file_paths']);
        unset($row['file_names']);

        $submissions[] = $row;
        $count++;
    }

    error_log("Found " . $count . " submissions");
    echo json_encode(['success' => true, 'data' => $submissions]);

} catch (Exception $e) {
    error_log("Error in get_coordinator_checklist.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} finally {
    if (isset($stmt)) {
        $stmt->close();
    }
    if (isset($conn)) {
        $conn->close();
    }
}
?> 