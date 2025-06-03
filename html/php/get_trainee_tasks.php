<?php
session_start();
require_once 'db_connect.php';
header('Content-Type: application/json');

// Check if user is logged in as trainee
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'trainee') {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized: Trainee access required.'
    ]);
    exit;
}

$trainee_id = $_SESSION['trainee_id'];

try {
    // Get task submissions with files
    $sql = "SELECT 
                ts.id,
                ts.submission_type,
                ts.comment,
                ts.status,
                ts.remarks,
                ts.date_submitted,
                GROUP_CONCAT(
                    JSON_OBJECT(
                        'file_id', tf.id,
                        'original_name', tf.original_name,
                        'file_name', tf.file_name,
                        'file_path', tf.file_path
                    )
                ) as files
            FROM task_submissions ts
            LEFT JOIN task_files tf ON ts.id = tf.submission_id
            WHERE ts.trainee_id = ?
            GROUP BY ts.id
            ORDER BY ts.date_submitted DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $trainee_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $tasks = [];
    while ($row = $result->fetch_assoc()) {
        // Parse the files JSON string into an array
        if ($row['files']) {
            $files_json = '[' . str_replace('}{', '},{', $row['files']) . ']';
            $row['files'] = json_decode($files_json, true);
        } else {
            $row['files'] = [];
        }
        $tasks[] = $row;
    }

    echo json_encode([
        'success' => true,
        'tasks' => $tasks
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching tasks: ' . $e->getMessage()
    ]);
} finally {
    if (isset($stmt)) $stmt->close();
    $conn->close();
} 