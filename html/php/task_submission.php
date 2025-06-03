<?php
session_start();
require_once 'db_connect.php';
header('Content-Type: application/json');

// Set timezone to Asia/Manila
date_default_timezone_set('Asia/Manila');

// Check if user is logged in as trainee
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'trainee') {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized: Trainee access required.'
    ]);
    exit;
}

$trainee_id = $_SESSION['trainee_id'];

// Check pre-requirements before allowing task submission
function checkPreRequirements($trainee_id, $conn) {
    $sql = "SELECT COALESCE(tr.status, 'Not Submitted') as status
            FROM requirements r
            LEFT JOIN trainee_requirements tr ON 
                r.requirement_name = tr.requirement_name AND 
                tr.trainee_id = ? AND
                tr.requirement_type = r.requirement_type
            WHERE r.requirement_type = 'pre'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $trainee_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        if ($row['status'] === 'Not Submitted' || $row['status'] === 'Pending' || $row['status'] === 'Rejected') {
            return false;
        }
    }
    return true;
}

if (!checkPreRequirements($trainee_id, $conn)) {
    echo json_encode([
        'success' => false,
        'message' => 'You cannot submit a task until all pre-requirements are approved.'
    ]);
    exit;
}

$action = isset($_POST['action']) ? $_POST['action'] : '';
$submission_type = isset($_POST['submission_type']) ? $_POST['submission_type'] : '';
$comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';

// Handle update action first
if ($action === 'update') {
    $task_id = isset($_POST['task_id']) ? intval($_POST['task_id']) : 0;
    if (!$task_id) {
        echo json_encode(['success' => false, 'message' => 'Missing task_id for update']);
        exit;
    }
    $uploadDir = '../uploads/tasks/';
    $allowed_types = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp'
    ];
    $max_file_size = 5 * 1024 * 1024;
    $uploaded_files = [];
    $errors = [];
    $keep_existing_files = isset($_POST['keep_existing_files']) && $_POST['keep_existing_files'] === 'true';

    if (isset($_FILES['files'])) {
        foreach ($_FILES['files']['tmp_name'] as $key => $tmp_name) {
            $file_name = $_FILES['files']['name'][$key];
            $file_size = $_FILES['files']['size'][$key];
            $file_type = $_FILES['files']['type'][$key];
            $file_error = $_FILES['files']['error'][$key];
            if ($file_error !== UPLOAD_ERR_OK) {
                $errors[] = "Error uploading file $file_name: " . upload_error_message($file_error);
                continue;
            }
            if ($file_size > $max_file_size) {
                $errors[] = "File $file_name is too large. Maximum size is 5MB.";
                continue;
            }
            $valid_type = false;
            foreach ($allowed_types as $ext => $mime_type) {
                if ($file_type === $mime_type) {
                    $valid_type = true;
                    break;
                }
            }
            if (!$valid_type) {
                $errors[] = "File type not allowed for $file_name. Allowed types: PDF, DOC, DOCX, JPG, PNG, WEBP";
                continue;
            }
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $unique_filename = uniqid('task_') . '.' . $file_ext;
            $upload_path = $uploadDir . $unique_filename;
            if (move_uploaded_file($tmp_name, $upload_path)) {
                $uploaded_files[] = [
                    'original_name' => $file_name,
                    'file_name' => $unique_filename,
                    'file_path' => 'uploads/tasks/' . $unique_filename
                ];
            } else {
                $errors[] = "Failed to save file $file_name";
            }
        }
    }
    if (!empty($errors)) {
        echo json_encode([
            'success' => false,
            'message' => implode("\n", $errors)
        ]);
        exit;
    }
    try {
        $conn->begin_transaction();
        $sql = "UPDATE task_submissions SET comment = ?, date_updated = NOW() WHERE id = ? AND trainee_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sis", $comment, $task_id, $trainee_id);
        if (!$stmt->execute()) {
            throw new Exception("Failed to update submission");
        }

        if (!empty($uploaded_files)) {
            if (!$keep_existing_files) {
                // Delete old files from server and DB only if not keeping existing files
                $old_file_sql = "SELECT file_path FROM task_files WHERE submission_id = ?";
                $old_file_stmt = $conn->prepare($old_file_sql);
                $old_file_stmt->bind_param("i", $task_id);
                $old_file_stmt->execute();
                $old_result = $old_file_stmt->get_result();
                while ($old_row = $old_result->fetch_assoc()) {
                    $old_file_path = '../' . $old_row['file_path'];
                    if (file_exists($old_file_path)) {
                        @unlink($old_file_path);
                    }
                }
                $old_file_stmt->close();
                $del_file_sql = "DELETE FROM task_files WHERE submission_id = ?";
                $del_file_stmt = $conn->prepare($del_file_sql);
                $del_file_stmt->bind_param("i", $task_id);
                $del_file_stmt->execute();
                $del_file_stmt->close();
            }
            
            // Insert new files
            $file_sql = "INSERT INTO task_files (submission_id, file_name, original_name, file_path) VALUES (?, ?, ?, ?)";
            $file_stmt = $conn->prepare($file_sql);
            foreach ($uploaded_files as $file) {
                $file_stmt->bind_param("isss", $task_id, $file['file_name'], $file['original_name'], $file['file_path']);
                if (!$file_stmt->execute()) {
                    throw new Exception("Failed to save file information");
                }
            }
            $file_stmt->close();
        }
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Submission updated successfully']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Error updating submission: ' . $e->getMessage()]);
    } finally {
        if (isset($stmt)) $stmt->close();
        $conn->close();
    }
    exit;
}

// Handle delete action next
if ($action === 'delete') {
    $task_id = isset($_POST['task_id']) ? intval($_POST['task_id']) : 0;
    if (!$task_id) {
        echo json_encode(['success' => false, 'message' => 'Missing task_id for delete']);
        exit;
    }
    try {
        $conn->begin_transaction();
        $file_sql = "SELECT file_path FROM task_files WHERE submission_id = ?";
        $file_stmt = $conn->prepare($file_sql);
        $file_stmt->bind_param("i", $task_id);
        $file_stmt->execute();
        $result = $file_stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $file_path = '../' . $row['file_path'];
            if (file_exists($file_path)) {
                @unlink($file_path);
            }
        }
        $del_file_sql = "DELETE FROM task_files WHERE submission_id = ?";
        $del_file_stmt = $conn->prepare($del_file_sql);
        $del_file_stmt->bind_param("i", $task_id);
        $del_file_stmt->execute();
        $del_sub_sql = "DELETE FROM task_submissions WHERE id = ? AND trainee_id = ?";
        $del_sub_stmt = $conn->prepare($del_sub_sql);
        $del_sub_stmt->bind_param("is", $task_id, $trainee_id);
        $del_sub_stmt->execute();
        if ($del_sub_stmt->affected_rows === 0) {
            throw new Exception("No submission found with ID: $task_id for this trainee.");
        }
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Submission deleted successfully']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Error deleting submission: ' . $e->getMessage()]);
    } finally {
        if (isset($file_stmt)) $file_stmt->close();
        if (isset($del_file_stmt)) $del_file_stmt->close();
        if (isset($del_sub_stmt)) $del_sub_stmt->close();
        $conn->close();
    }
    exit;
}

// Only validate submission_type for new submissions
if ($action !== 'update' && $action !== 'delete') {
    if (!in_array($submission_type, ['Weekly Report', 'Submission of Deliverables'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid submission type'
        ]);
        exit;
    }
    // Validate comment for new submissions
    if (empty($comment)) {
        echo json_encode([
            'success' => false,
            'message' => 'Please provide a description or comment'
        ]);
        exit;
    }
}

$uploadDir = '../uploads/tasks/';

// Create upload directory if it doesn't exist
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Allowed file types
$allowed_types = [
    'pdf' => 'application/pdf',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'webp' => 'image/webp'
];

// Maximum file size (5MB)
$max_file_size = 5 * 1024 * 1024;

$uploaded_files = [];
$errors = [];

// Process each uploaded file
if (isset($_FILES['files'])) {
    foreach ($_FILES['files']['tmp_name'] as $key => $tmp_name) {
        $file_name = $_FILES['files']['name'][$key];
        $file_size = $_FILES['files']['size'][$key];
        $file_type = $_FILES['files']['type'][$key];
        $file_error = $_FILES['files']['error'][$key];

        // Basic error checking
        if ($file_error !== UPLOAD_ERR_OK) {
            $errors[] = "Error uploading file $file_name: " . upload_error_message($file_error);
            continue;
        }

        // File size validation
        if ($file_size > $max_file_size) {
            $errors[] = "File $file_name is too large. Maximum size is 5MB.";
            continue;
        }

        // File type validation
        $valid_type = false;
        foreach ($allowed_types as $ext => $mime_type) {
            if ($file_type === $mime_type) {
                $valid_type = true;
                break;
            }
        }

        if (!$valid_type) {
            $errors[] = "File type not allowed for $file_name. Allowed types: PDF, DOC, DOCX, JPG, PNG, WEBP";
            continue;
        }

        // Generate unique filename
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $unique_filename = uniqid('task_') . '.' . $file_ext;
        $upload_path = $uploadDir . $unique_filename;

        // Move file to uploads directory
        if (move_uploaded_file($tmp_name, $upload_path)) {
            $uploaded_files[] = [
                'original_name' => $file_name,
                'file_name' => $unique_filename,
                'file_path' => 'uploads/tasks/' . $unique_filename
            ];
        } else {
            $errors[] = "Failed to save file $file_name";
        }
    }
}

// Only require at least one file for new submissions
if ($action !== 'update' && $action !== 'delete') {
    if (empty($uploaded_files)) {
        echo json_encode([
            'success' => false,
            'message' => 'Please upload at least one file'
        ]);
        exit;
    }
}

if (!empty($errors)) {
    echo json_encode([
        'success' => false,
        'message' => implode("\n", $errors)
    ]);
    exit;
}

// Save submission to database
try {
    $conn->begin_transaction();

    // Insert task submission
    $sql = "INSERT INTO task_submissions (trainee_id, submission_type, comment, status, remarks) 
            VALUES (?, ?, ?, 'Pending', 'Under review')";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $trainee_id, $submission_type, $comment);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to save submission to database");
    }

    $submission_id = $conn->insert_id;

    // Insert files
    $file_sql = "INSERT INTO task_files (submission_id, file_name, original_name, file_path) VALUES (?, ?, ?, ?)";
    $file_stmt = $conn->prepare($file_sql);

    foreach ($uploaded_files as $file) {
        $file_stmt->bind_param("isss", 
            $submission_id,
            $file['file_name'],
            $file['original_name'],
            $file['file_path']
        );
        
        if (!$file_stmt->execute()) {
            throw new Exception("Failed to save file information");
        }
    }

    // Get trainee name for notification
    $trainee_sql = "SELECT full_name FROM trainees WHERE trainee_id = ?";
    $trainee_stmt = $conn->prepare($trainee_sql);
    $trainee_stmt->bind_param("s", $trainee_id);
    $trainee_stmt->execute();
    $trainee_result = $trainee_stmt->get_result();
    $trainee = $trainee_result->fetch_assoc();
    $trainee_name = $trainee['full_name'];

    // Send notification to coordinator
    $notification_sql = "INSERT INTO notifications (user_id, user_role, message, link, created_at)
                        SELECT c.user_id, 'coordinator',
                        CONCAT(?, ' has submitted a new ', ?),
                        CONCAT('/coordinator_SubmissionOverviewHistory.html?trainee_id=', ?),
                        NOW()
                        FROM trainees t
                        JOIN section_assignments sa ON t.program = sa.program AND t.section = sa.section
                        JOIN coordinators c ON sa.coordinator_id = c.coordinator_id
                        WHERE t.trainee_id = ?";
                        
    $notification_stmt = $conn->prepare($notification_sql);
    $notification_stmt->bind_param("ssss", $trainee_name, $submission_type, $trainee_id, $trainee_id);
    
    if (!$notification_stmt->execute()) {
        throw new Exception("Failed to send notification");
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Task submitted successfully'
    ]);
    exit;

} catch (Exception $e) {
    $conn->rollback();
    
    // Delete uploaded files if database operation failed
    foreach ($uploaded_files as $file) {
        @unlink($uploadDir . $file['file_name']);
    }

    echo json_encode([
        'success' => false,
        'message' => 'Error saving submission: ' . $e->getMessage()
    ]);
} finally {
    if (isset($stmt)) $stmt->close();
    $conn->close();
}

// Helper function for upload error messages
function upload_error_message($error_code) {
    switch ($error_code) {
        case UPLOAD_ERR_INI_SIZE:
            return 'The uploaded file exceeds the upload_max_filesize directive in php.ini';
        case UPLOAD_ERR_FORM_SIZE:
            return 'The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form';
        case UPLOAD_ERR_PARTIAL:
            return 'The uploaded file was only partially uploaded';
        case UPLOAD_ERR_NO_FILE:
            return 'No file was uploaded';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Missing a temporary folder';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Failed to write file to disk';
        case UPLOAD_ERR_EXTENSION:
            return 'A PHP extension stopped the file upload';
        default:
            return 'Unknown upload error';
    }
}
?> 