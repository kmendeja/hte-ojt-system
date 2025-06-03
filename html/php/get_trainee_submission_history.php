<?php
session_start();
require_once 'db_connect.php';
header('Content-Type: application/json');

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'coordinator') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$coordinator_id = $_SESSION['user_id'];
$trainee_id = $_GET['trainee_id'] ?? '';

if (empty($trainee_id)) {
    echo json_encode(['success' => false, 'message' => 'Trainee ID is required']);
    exit;
}

// Verify coordinator has access to this trainee
$sql = "SELECT 1 FROM section_assignments sa 
        JOIN coordinators c ON sa.coordinator_id = c.coordinator_id
        JOIN trainees t ON t.program = sa.program AND t.section = sa.section
        WHERE c.user_id = ? AND t.trainee_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $coordinator_id, $trainee_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access to this trainee']);
    exit;
}

// Get trainee information
$sql = "SELECT t.*, c.name as company_name 
        FROM trainees t 
        LEFT JOIN companies c ON t.company_id = c.id
        WHERE t.trainee_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $trainee_id);
$stmt->execute();
$trainee = $stmt->get_result()->fetch_assoc();

error_log("Getting submissions for trainee: " . $trainee_id);

// Get submission history
$sql = "SELECT r.*, tr.id as submission_id, tr.file_name, tr.file_path, 
        tr.status, tr.remarks, tr.date_submitted, tr.date_updated,
        CASE 
            WHEN tr.id IS NULL THEN 'Not Submitted'
            WHEN tr.status = 'Pending' THEN 'Pending / Under Review'
            WHEN tr.status = 'Approved' THEN 'Approved'
            WHEN tr.status = 'Rejected' THEN 'Rejected'
            ELSE 'Not Submitted'
        END as display_status,
        CASE 
            WHEN tr.id IS NULL THEN 'Requirement not yet submitted'
            ELSE COALESCE(tr.remarks, 'Under review')
        END as display_remarks
        FROM requirements r
        LEFT JOIN trainee_requirements tr ON 
            r.requirement_name = tr.requirement_name AND 
            r.requirement_type = tr.requirement_type AND 
            tr.trainee_id = ?
        ORDER BY r.requirement_type, r.position";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $trainee_id);
$stmt->execute();
$result = $stmt->get_result();

$submissions = [];
while ($row = $result->fetch_assoc()) {
    $submissions[] = [
        'id' => $row['id'],
        'requirement_name' => $row['requirement_name'],
        'requirement_type' => $row['requirement_type'],
        'submission_id' => $row['submission_id'],
        'file_name' => $row['file_name'],
        'file_path' => $row['file_path'],
        'status' => $row['display_status'],
        'remarks' => $row['display_remarks'],
        'date_submitted' => $row['date_submitted'],
        'date_updated' => $row['date_updated']
    ];
}

echo json_encode([
    'success' => true,
    'trainee' => $trainee,
    'submissions' => $submissions
]);
?> 