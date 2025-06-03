<?php
session_start();
require_once 'db_connect.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!isset($_FILES['csv']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No CSV file uploaded.']);
    exit;
}

$file = $_FILES['csv']['tmp_name'];
$handle = fopen($file, 'r');
if (!$handle) {
    echo json_encode(['success' => false, 'message' => 'Failed to open CSV file.']);
    exit;
}

$header = fgetcsv($handle);
$required = ['employee_id', 'full_name', 'job_description', 'contact_number', 'email', 'username', 'password'];
foreach ($required as $col) {
    if (!in_array($col, $header)) {
        echo json_encode(['success' => false, 'message' => 'Missing required column: ' . $col]);
        fclose($handle);
        exit;
    }
}

$successCount = 0;
$failures = [];
while (($row = fgetcsv($handle)) !== false) {
    $data = array_combine($header, $row);
    try {
        // 1. Create user
        $username = $data['username'];
        $password = 'password123'; // Always use default password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $role = 'coordinator';
        $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $username, $hashedPassword, $role);
        $stmt->execute();
        $user_id = $stmt->insert_id;
        // 2. Create coordinator
        $stmt = $conn->prepare("INSERT INTO coordinators (user_id, full_name, job_description, employee_id, contact_number, email, account_created) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param(
            "issssss",
            $user_id,
            $data['full_name'],
            $data['job_description'],
            $data['employee_id'],
            $data['contact_number'],
            $data['email']
        );
        $stmt->execute();
        $successCount++;
    } catch (Exception $e) {
        $failures[] = [
            'row' => $data,
            'error' => $e->getMessage()
        ];
    }
}
fclose($handle);
$conn->close();
echo json_encode([
    'success' => true,
    'added' => $successCount,
    'failures' => $failures
]);
?> 