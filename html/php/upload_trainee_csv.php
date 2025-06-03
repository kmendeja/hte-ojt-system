<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Prevent any unwanted output
ob_start();

try {
    require_once 'db_connect.php';

    // Clear any previous output
    ob_clean();

    header('Content-Type: application/json');

    if (!isset($_FILES['file'])) {
        throw new Exception('No file uploaded');
    }

    $file = $_FILES['file'];
    $uploadError = '';
    
    // Check for PHP upload errors and provide specific messages
    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_INI_SIZE:
            $uploadError = 'The uploaded file exceeds the upload_max_filesize directive in php.ini';
            break;
        case UPLOAD_ERR_FORM_SIZE:
            $uploadError = 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form';
            break;
        case UPLOAD_ERR_PARTIAL:
            $uploadError = 'The uploaded file was only partially uploaded';
            break;
        case UPLOAD_ERR_NO_FILE:
            $uploadError = 'No file was uploaded';
            break;
        case UPLOAD_ERR_NO_TMP_DIR:
            $uploadError = 'Missing a temporary folder';
            break;
        case UPLOAD_ERR_CANT_WRITE:
            $uploadError = 'Failed to write file to disk';
            break;
        case UPLOAD_ERR_EXTENSION:
            $uploadError = 'A PHP extension stopped the file upload';
            break;
        default:
            $uploadError = 'Unknown upload error';
    }

    if ($uploadError !== '') {
        throw new Exception('File upload failed: ' . $uploadError);
    }

    // Verify file extension
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($extension !== 'csv') {
        throw new Exception('Invalid file type. Please upload a CSV file.');
    }

    // Read CSV file
    $handle = fopen($file['tmp_name'], 'r');
    if ($handle === false) {
        throw new Exception('Failed to read CSV file: ' . error_get_last()['message']);
    }

    // Read header row
    $headers = fgetcsv($handle);
    if ($headers === false) {
        fclose($handle);
        throw new Exception('Empty CSV file or invalid format');
    }

    // Clean headers
    $headers = array_map(function($header) {
        return trim(strtolower($header));
    }, $headers);

    // Expected headers
    $expectedHeaders = [
        'student_id', 'program', 'section',
        'last_name', 'first_name', 'middle_name', 'extension_name',
        'email', 'username', 'contact_number'
    ];

    // Verify headers
    $missingHeaders = array_diff($expectedHeaders, $headers);
    if (!empty($missingHeaders)) {
        fclose($handle);
        throw new Exception('Missing required columns: ' . implode(', ', $missingHeaders));
    }

    // Test database connection
    if (!$conn) {
        throw new Exception("Database connection failed: " . mysqli_connect_error());
    }

    // Begin transaction
    if (!mysqli_begin_transaction($conn)) {
        throw new Exception("Failed to start transaction: " . mysqli_error($conn));
    }

    $rowCount = 0;
    $errors = [];
    $skippedCount = 0;
    $addedCount = 0;

    // Process each row
    while (($row = fgetcsv($handle)) !== false) {
        $rowCount++;
        
        try {
            if (empty(array_filter($row))) {
                continue;
            }

            $data = array_combine($headers, $row);

            // Validate required fields
            $requiredFields = ['student_id', 'program', 'section',
                             'last_name', 'first_name', 'email', 'username', 
                             'contact_number'];
            
            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    throw new Exception("Row $rowCount: $field is required");
                }
            }

            // Validate email format
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Row $rowCount: Invalid email format");
            }

            // Validate contact number (11 digits)
            if (!preg_match('/^[0-9]{11}$/', $data['contact_number'])) {
                throw new Exception("Row $rowCount: Contact number must be 11 digits");
            }

            // Check for duplicate trainee ID
            $stmt = mysqli_prepare($conn, "SELECT COUNT(*) as count FROM trainees WHERE trainee_id = ?");
            if (!$stmt) {
                throw new Exception("Database error: " . mysqli_error($conn));
            }
            mysqli_stmt_bind_param($stmt, "s", $data['student_id']);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            if (mysqli_fetch_assoc($result)['count'] > 0) {
                $skippedCount++;
                mysqli_stmt_close($stmt);
                continue; // Skip this row and continue with the next one
            }
            mysqli_stmt_close($stmt);

            // Create user account
            $defaultPassword = 'password123';
            $hashedPassword = password_hash($defaultPassword, PASSWORD_DEFAULT);

            $stmt = mysqli_prepare($conn, "INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'trainee')");
            if (!$stmt) {
                throw new Exception("Database error: " . mysqli_error($conn));
            }
            mysqli_stmt_bind_param($stmt, "sss", 
                $data['username'],
                $data['email'],
                $hashedPassword
            );
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Row $rowCount: Error creating user account: " . mysqli_error($conn));
            }
            
            $userId = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);

            // Construct full name
            $fullName = $data['last_name'] . ', ' . $data['first_name'];
            if (!empty($data['middle_name'])) {
                $fullName .= ' ' . substr($data['middle_name'], 0, 1) . '.';
            }
            if (!empty($data['extension_name']) && $data['extension_name'] !== 'None') {
                $fullName .= ' ' . $data['extension_name'];
            }

            // Default values
            $school = "PAMANTASAN NG LUNGSOD NG PASIG";
            $status = "Trainee";

            // Insert trainee record
            $stmt = mysqli_prepare($conn, "INSERT INTO trainees (
                trainee_id, full_name, program, section, school,
                contact_number, email, username, profile_image,
                status, user_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            if (!$stmt) {
                throw new Exception("Database error: " . mysqli_error($conn));
            }

            mysqli_stmt_bind_param($stmt, "sssssssssss",
                $data['student_id'],
                $fullName,
                $data['program'],
                $data['section'],
                $school,
                $data['contact_number'],
                $data['email'],
                $data['username'],
                $status,
                $userId
            );

            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Row $rowCount: Error inserting trainee: " . mysqli_error($conn));
            }
            mysqli_stmt_close($stmt);
            $addedCount++;

        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    }

    fclose($handle);

    if (!empty($errors)) {
        throw new Exception("Errors occurred while processing the CSV:\n" . implode("\n", $errors));
    }

    if (!mysqli_commit($conn)) {
        throw new Exception("Failed to commit transaction: " . mysqli_error($conn));
    }

    // Clear any previous output before sending JSON response
    ob_clean();
    
    echo json_encode([
        'success' => true,
        'message' => "Successfully imported $addedCount trainees. Skipped $skippedCount duplicate entries."
    ]);

} catch (Exception $e) {
    if (isset($conn) && !mysqli_connect_errno()) {
        mysqli_rollback($conn);
    }
    
    // Clear any previous output before sending JSON response
    ob_clean();
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// Close the connection
if (isset($conn)) {
    mysqli_close($conn);
}

// Flush output buffer
ob_end_flush();
?> 