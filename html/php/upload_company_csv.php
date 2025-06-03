<?php
require_once 'db_connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['csv']) || $_FILES['csv']['error'] !== 0) {
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
$expected = ['name','effective_date','address','corporate_email','corporate_contact','contact_person','position','contact_number','email'];
$missing = array_diff($expected, $header);
$extra = array_diff($header, $expected);
if (!empty($missing)) {
    echo json_encode(['success' => false, 'message' => 'CSV upload failed: Missing columns: ' . implode(', ', $missing)]);
    fclose($handle);
    exit;
}
if (!empty($extra)) {
    echo json_encode(['success' => false, 'message' => 'CSV upload failed: Unexpected columns: ' . implode(', ', $extra)]);
    fclose($handle);
    exit;
}

function checkDuplicateCompanyName($conn, $name) {
    $sql = "SELECT COUNT(*) as count FROM companies WHERE name = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $name);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['count'] > 0;
}

$added = 0;
$failures = [];
$rowNum = 1;
while (($row = fgetcsv($handle)) !== false) {
    $rowNum++;
    if (count($row) !== count($header)) {
        $failures[] = 'Row ' . $rowNum . ': column count does not match header.';
        continue;
    }
    $data = array_combine($header, $row);
    if (!$data) {
        $failures[] = 'Row ' . $rowNum . ': invalid row data.';
        continue;
    }
    // Duplicate check before insert
    if (checkDuplicateCompanyName($conn, $data['name'])) {
        $failures[] = 'Row ' . $rowNum . ': Duplicate company name found: ' . ($data['name'] ?? 'Unknown');
        continue;
    }
    $insert = $conn->prepare("INSERT INTO companies (name, effective_date, address, corporate_email, corporate_contact, contact_person, position, contact_number, email) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $insert->bind_param(
        "sssssssss",
        $data['name'],
        $data['effective_date'],
        $data['address'],
        $data['corporate_email'],
        $data['corporate_contact'],
        $data['contact_person'],
        $data['position'],
        $data['contact_number'],
        $data['email']
    );
    if ($insert->execute()) {
        $added++;
    } else {
        $failures[] = 'Row ' . $rowNum . ': ' . ($data['name'] ?? 'Unknown') . ' - ' . $insert->error;
    }
}
fclose($handle);
echo json_encode([
    'success' => $added > 0,
    'added' => $added,
    'failures' => $failures,
    'message' => $added > 0 ? 'CSV upload completed.' : 'No companies added.'
]);
exit; 