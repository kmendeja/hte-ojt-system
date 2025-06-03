<?php
session_start();
require_once 'db_connect.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'trainee') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$trainee_id = $_SESSION['trainee_id'] ?? '';

if (empty($trainee_id)) {
    echo json_encode(['success' => false, 'message' => 'Trainee ID not found in session']);
    exit;
}

// Get trainee's assigned coordinator
$sql = "SELECT c.coordinator_id, c.full_name as coordinator_name, c.email as coordinator_email,
        t.trainee_id, t.full_name, t.program, t.section, t.contact_number, t.email,
        t.company_id, comp.name as company_name
        FROM trainees t
        JOIN section_assignments sa ON 
            t.program = sa.program AND 
            t.section = sa.section
        JOIN coordinators c ON sa.coordinator_id = c.coordinator_id
        LEFT JOIN companies comp ON t.company_id = comp.id
        WHERE t.trainee_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $trainee_id);
$stmt->execute();
$result = $stmt->get_result();
$trainee_info = $result->fetch_assoc();

if (!$trainee_info) {
    echo json_encode(['success' => false, 'message' => 'Trainee information not found']);
    exit;
}

// Get all requirements
$sql = "SELECT * FROM requirements ORDER BY requirement_type, id";
$result = $conn->query($sql);
$requirements = [];
while ($row = $result->fetch_assoc()) {
    $requirements[] = $row;
}

// Get trainee's submissions
$sql = "SELECT tr.*, 
        CASE 
            WHEN tr.status = 'Pending' THEN 'warning'
            WHEN tr.status = 'Approved' THEN 'success'
            WHEN tr.status = 'Rejected' THEN 'danger'
            ELSE 'info'
        END as status_class
        FROM trainee_requirements tr
        WHERE tr.trainee_id = ?
        ORDER BY tr.date_submitted DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $trainee_id);
$stmt->execute();
$result = $stmt->get_result();

$submissions = [];
while ($row = $result->fetch_assoc()) {
    $submissions[] = $row;
}

// Count submitted requirements
$submitted_count = count($submissions);
$total_requirements = count($requirements);

echo json_encode([
    'success' => true,
    'trainee' => $trainee_info,
    'requirements' => $requirements,
    'submissions' => $submissions,
    'submission_status' => [
        'submitted' => $submitted_count,
        'total' => $total_requirements
    ]
]);
?> 