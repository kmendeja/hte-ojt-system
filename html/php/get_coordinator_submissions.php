<?php
session_start();
require_once 'db_connect.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'coordinator') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$coordinator_id = $_SESSION['user_id'];
$sort_by = $_GET['sort_by'] ?? '';

// Get sections assigned to coordinator
$sql = "SELECT program, section FROM section_assignments WHERE coordinator_id = (
    SELECT coordinator_id FROM coordinators WHERE user_id = ?
)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $coordinator_id);
$stmt->execute();
$result = $stmt->get_result();

$assigned_sections = [];
while ($row = $result->fetch_assoc()) {
    $assigned_sections[] = [
        'program' => $row['program'],
        'section' => $row['section']
    ];
}

// Build the query to get trainees from assigned sections
$trainee_conditions = [];
foreach ($assigned_sections as $section) {
    $trainee_conditions[] = "(program = '{$section['program']}' AND section = '{$section['section']}')";
}

if (empty($trainee_conditions)) {
    echo json_encode(['success' => true, 'data' => []]);
    exit;
}

$trainee_condition = implode(' OR ', $trainee_conditions);

// Base query to get trainee information
$sql = "SELECT t.trainee_id, t.full_name, t.program, t.section, t.contact_number, t.email, t.profile_image
        FROM trainees t 
        WHERE $trainee_condition";

// Add sorting
switch ($sort_by) {
    case 'id':
        $sql .= " ORDER BY t.trainee_id";
        break;
    case 'ascending':
        $sql .= " ORDER BY t.full_name ASC";
        break;
    case 'descending':
        $sql .= " ORDER BY t.full_name DESC";
        break;
}

$result = $conn->query($sql);
$trainees = [];

while ($row = $result->fetch_assoc()) {
    $row['submission_status'] = "docs submitted";
    $row['profile_image'] = $row['profile_image'] ? $row['profile_image'] : 'icons/default_profile.png';
    $trainees[] = $row;
}

echo json_encode(['success' => true, 'data' => $trainees]);
?> 