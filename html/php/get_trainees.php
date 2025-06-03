<?php
session_start();
header('Content-Type: application/json');
require_once 'db_connect.php';

try {
    // Check if user is logged in as admin or coordinator
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'coordinator'])) {
        throw new Exception('Unauthorized access');
    }

    // If admin, return all trainees
    if ($_SESSION['role'] === 'admin') {
        $query = "SELECT 
            t.trainee_id,
            t.full_name,
            t.program,
            t.section,
            t.contact_number,
            t.email,
            t.profile_image,
            t.username,
            c.name as company_assigned,
            t.nature_of_work
        FROM trainees t
        LEFT JOIN companies c ON t.company_id = c.id
        ORDER BY t.trainee_id";
        $result = $conn->query($query);
        if (!$result) {
            throw new Exception("Query failed: " . $conn->error);
        }
        $trainees = [];
        while ($row = $result->fetch_assoc()) {
            $trainees[] = [
                'trainee_id' => $row['trainee_id'],
                'full_name' => $row['full_name'],
                'program' => $row['program'],
                'section' => $row['section'],
                'contact_number' => $row['contact_number'],
                'email' => $row['email'],
                'profile_image' => $row['profile_image'] ?: 'icons/default_profile.png',
                'username' => $row['username'],
                'company_assigned' => $row['company_assigned'] ?: 'Not Assigned',
                'nature_of_work' => $row['nature_of_work'] ?: 'Not Specified'
            ];
        }
        echo json_encode([
            'success' => true,
            'trainees' => $trainees
        ]);
        $conn->close();
        exit;
    }

    // If coordinator, keep current logic
    $coordinator_id = $_SESSION['user_id'];
    $sql = "SELECT program, section
            FROM section_assignments sa
            JOIN coordinators c ON sa.coordinator_id = c.coordinator_id
            WHERE c.user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $coordinator_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $assigned_sections = [];
    while ($row = $result->fetch_assoc()) {
        $assigned_sections[] = [
            'program' => $row['program'],
            'section' => $row['section'],
        ];
    }
    if (empty($assigned_sections)) {
        echo json_encode([
            'success' => true,
            'trainees' => []
        ]);
        $conn->close();
        exit;
    }
    $where_conditions = [];
    $params = [];
    $types = "";
    foreach ($assigned_sections as $section) {
        $where_conditions[] = "(program = ? AND section = ?)";
        $params[] = $section['program'];
        $params[] = $section['section'];
        $types .= "ss";
    }
    $query = "SELECT 
        t.trainee_id,
        t.full_name,
        t.program,
        t.section,
        t.contact_number,
        t.email,
        t.profile_image,
        t.username,
        c.name as company_assigned,
        t.nature_of_work
    FROM trainees t
    LEFT JOIN companies c ON t.company_id = c.id
    WHERE " . implode(" OR ", $where_conditions) . "
    ORDER BY t.trainee_id";
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    if (!$result) {
        throw new Exception("Query failed: " . $conn->error);
    }
    $trainees = [];
    while ($row = $result->fetch_assoc()) {
        $trainees[] = [
            'trainee_id' => $row['trainee_id'],
            'full_name' => $row['full_name'],
            'program' => $row['program'],
            'section' => $row['section'],
            'contact_number' => $row['contact_number'],
            'email' => $row['email'],
            'profile_image' => $row['profile_image'] ?: 'icons/default_profile.png',
            'username' => $row['username'],
            'company_assigned' => $row['company_assigned'] ?: 'Not Assigned',
            'nature_of_work' => $row['nature_of_work'] ?: 'Not Specified'
        ];
    }
    echo json_encode([
        'success' => true,
        'trainees' => $trainees
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

$conn->close();
?> 