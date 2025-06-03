<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Include database connection
require_once 'db_connect.php';

// Check if user is logged in as trainee
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'trainee') {
    die(json_encode(['success' => false, 'message' => 'Unauthorized access']));
}

try {
    // Support both single and multiple uploads
    $requirement_ids = isset($_POST['requirement_id']) ? (array)$_POST['requirement_id'] : (isset($_POST['requirements']) ? $_POST['requirements'] : []);

    // Normalize files array for robust handling
    function normalize_files_array($files) {
        $normalized = [];
        if (is_array($files['name'])) {
            $file_count = count($files['name']);
            for ($i = 0; $i < $file_count; $i++) {
                $normalized[] = [
                    'name' => $files['name'][$i],
                    'type' => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error' => $files['error'][$i],
                    'size' => $files['size'][$i],
                ];
            }
        } else {
            $normalized[] = $files;
        }
        return $normalized;
    }

    $normalized_files = [];
    if (isset($_FILES['file'])) {
        $normalized_files = normalize_files_array($_FILES['file']);
    } elseif (isset($_FILES['files'])) {
        $normalized_files = normalize_files_array($_FILES['files']);
    }

    if (empty($requirement_ids) || empty($normalized_files)) {
        throw new Exception('Missing required fields');
    }

    // Get trainee_id as code (e.g., 23-001026) from session or by querying trainees table
    $trainee_id = isset($_SESSION['trainee_id']) ? $_SESSION['trainee_id'] : null;
    if (!$trainee_id) {
        $user_id = $_SESSION['user_id'];
        $sql = "SELECT trainee_id FROM trainees WHERE user_id = $user_id LIMIT 1";
        $result = $conn->query($sql);
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $trainee_id = $row['trainee_id'];
        } else {
            throw new Exception('Trainee ID not found for user.');
        }
    }

    $results = [];

    // Normalize requirements array
    $requirement_ids = array_values($requirement_ids);
    $fileCount = count($normalized_files);
    $reqCount = count($requirement_ids);
    $loopCount = max($fileCount, $reqCount);
    for ($i = 0; $i < $loopCount; $i++) {
        $req_id = isset($requirement_ids[$i]) ? (int)$requirement_ids[$i] : (isset($requirement_ids[0]) ? (int)$requirement_ids[0] : null);
        $file = isset($normalized_files[$i]) ? $normalized_files[$i] : (isset($normalized_files[0]) ? $normalized_files[0] : null);
        if (!$req_id || !$file) {
            $results[] = ['success' => false, 'message' => 'Missing requirement ID or file for item #' . ($i+1)];
            error_log('Missing requirement ID or file for item #' . ($i+1));
            continue;
        }

        // Get requirement details
        $sql = "SELECT * FROM requirements WHERE id = $req_id";
        $result = $conn->query($sql);
        if (!$result || $result->num_rows === 0) {
            $results[] = ['success' => false, 'message' => 'Requirement not found for ID ' . $req_id . ' (file: ' . $file['name'] . ')'];
            error_log('Requirement not found for ID ' . $req_id . ' (file: ' . $file['name'] . ')');
            continue;
        }
        $requirement = $result->fetch_assoc();
        $requirement_type = strtolower($conn->real_escape_string($requirement['requirement_type']));
        $requirement_name = $conn->real_escape_string($requirement['requirement_name']);
        error_log('Processing file #' . ($i+1) . ': ' . $file['name'] . ' for requirement_id=' . $req_id . ' (' . $requirement_name . ', ' . $requirement_type . ')');

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $results[] = ['success' => false, 'message' => 'File upload failed for ' . $file['name']];
            continue;
        }
        if ($file['size'] > 5 * 1024 * 1024) {
            $results[] = ['success' => false, 'message' => 'File too large: ' . $file['name']];
            continue;
        }
        // Allowed file types (add DOCX if needed)
        $allowed_types = [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' // DOCX
        ];
        if (!in_array($file['type'], $allowed_types)) {
            $results[] = ['success' => false, 'message' => 'Invalid file type: ' . $file['name']];
            continue;
        }

        $base_upload_dir = __DIR__ . '/../../uploads';
        if (!is_dir($base_upload_dir)) {
            mkdir($base_upload_dir, 0777, true);
        }
        $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $clean_name = sanitize_filename($trainee_id . '_' . $requirement_name);
        $filename = $clean_name . '.' . $file_extension;
        // Set upload directory for pre-requirements to html/uploads/pre
        if ($requirement_type === 'pre') {
            $type_dir = __DIR__ . '/../uploads/pre';
            if (!is_dir($type_dir)) {
                mkdir($type_dir, 0777, true);
            }
            $relative_filepath = 'html/uploads/pre/' . $filename;
        } else {
            $type_dir = $base_upload_dir . '/' . $requirement_type;
            if (!is_dir($type_dir)) {
                mkdir($type_dir, 0777, true);
            }
            $relative_filepath = 'uploads/' . $requirement_type . '/' . $filename;
        }
        $filepath = $type_dir . '/' . $filename;
        if (file_exists($filepath)) {
            $timestamp = time();
            $filename = $clean_name . '_' . $timestamp . '.' . $file_extension;
            $filepath = $type_dir . '/' . $filename;
            if ($requirement_type === 'pre') {
                $relative_filepath = 'html/uploads/pre/' . $filename;
            } else {
                $relative_filepath = 'uploads/' . $requirement_type . '/' . $filename;
            }
        }
        // Use @ to suppress PHP warnings
        if (!@move_uploaded_file($file['tmp_name'], $filepath)) {
            $results[] = ['success' => false, 'message' => 'Failed to save file: ' . $file['name']];
            continue;
        }
        $sql = "SELECT * FROM trainee_requirements 
                WHERE trainee_id = '$trainee_id' 
                AND requirement_name = '$requirement_name' 
                AND requirement_type = '$requirement_type'";
        $result = $conn->query($sql);
        if ($result && $result->num_rows > 0) {
            $old = $result->fetch_assoc();
            if (!empty($old['file_path'])) {
                $old_path = ($requirement_type === 'pre')
                    ? __DIR__ . '/../' . $old['file_path']
                    : $base_upload_dir . '/' . $old['file_path'];
                if (file_exists($old_path)) {
                    @unlink($old_path);
                }
            }
            $sql = "UPDATE trainee_requirements 
                    SET file_path = '$relative_filepath', 
                        file_name = '{$file['name']}',
                        status = 'pending', 
                        date_submitted = NOW() 
                    WHERE trainee_id = '$trainee_id' 
                    AND requirement_name = '$requirement_name' 
                    AND requirement_type = '$requirement_type'";
        } else {
            $sql = "INSERT INTO trainee_requirements 
                    (trainee_id, requirement_name, requirement_type, file_path, file_name, status, date_submitted) 
                    VALUES ('$trainee_id', '$requirement_name', '$requirement_type', '$relative_filepath', '{$file['name']}', 'pending', NOW())";
        }
        if (!$conn->query($sql)) {
            unlink($filepath);
            $results[] = ['success' => false, 'message' => 'Failed to update database for ' . $file['name'] . ' (requirement: ' . $requirement_name . ')'];
            error_log('Failed to update database for ' . $file['name'] . ' (requirement: ' . $requirement_name . ')');
            continue;
        }
        $results[] = ['success' => true, 'message' => 'Requirement submitted: ' . $requirement_name . ' (file: ' . $file['name'] . ')'];
        error_log('Requirement submitted: ' . $requirement_name . ' (file: ' . $file['name'] . ')');
    }

    echo json_encode([
        'success' => true,
        'results' => $results
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function sanitize_filename($filename) {
    // Remove anything which isn't a word, whitespace, number, dot, or dash
    $filename = preg_replace("([^\w\s\d\-_~,;\[\]\(\).])", '', $filename);
    // Remove any runs of periods
    $filename = preg_replace("([\.]{2,})", '', $filename);
    // Replace spaces with underscores
    $filename = str_replace(' ', '_', $filename);
    return $filename;
}
?> 