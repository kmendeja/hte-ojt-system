<?php
require_once 'db_connect.php';

// Ensure no output before JSON response
error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json');

try {
    // Validate required fields
    $requiredFields = [
        'student_id', 'program', 'section',
        'last_name', 'first_name', 'email', 'username',
        'contact_number'
    ];

    foreach ($requiredFields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            throw new Exception("$field is required");
        }
    }

    // Start transaction
    $conn->begin_transaction();

    // Check if trainee ID already exists
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM trainees WHERE trainee_id = ?");
    $stmt->bind_param("s", $_POST['student_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    if ($row['count'] > 0) {
        throw new Exception('A trainee with this ID already exists');
    }

    // Check if username already exists
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE username = ?");
    $stmt->bind_param("s", $_POST['username']);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    if ($row['count'] > 0) {
        throw new Exception('Username already taken');
    }

    // Check if email already exists
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE email = ?");
    $stmt->bind_param("s", $_POST['email']);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    if ($row['count'] > 0) {
        throw new Exception('Email already registered');
    }

    // Handle profile image upload if provided
    $profileImage = null;
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['profile_image'];
        
        // Verify file type
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $fileType = mime_content_type($file['tmp_name']);
        if (!in_array($fileType, $allowedTypes)) {
            throw new Exception('Invalid file type. Please upload a JPEG, PNG, GIF, or WebP image.');
        }

        // Create upload directory if it doesn't exist
        $uploadDir = __DIR__ . '/../profiles_trainee/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = $_POST['student_id'] . '_' . time() . '.' . $extension;
        $targetPath = $uploadDir . $filename;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new Exception('Failed to save the profile image');
        }

        $profileImage = 'profiles_trainee/' . $filename;
    }

    // Hash password (using default password)
    $defaultPassword = 'password123';
    $hashedPassword = password_hash($defaultPassword, PASSWORD_DEFAULT);

    // First, create the user account
    $stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'trainee')");
    $stmt->bind_param("sss",
        $_POST['username'],
        $_POST['email'],
        $hashedPassword
    );

    if (!$stmt->execute()) {
        throw new Exception("Error creating user account: " . $stmt->error);
    }

    $userId = $conn->insert_id;

    // Construct full name
    $fullName = $_POST['last_name'] . ', ' . $_POST['first_name'];
    if (!empty($_POST['middle_name'])) {
        $fullName .= ' ' . substr($_POST['middle_name'], 0, 1) . '.';
    }
    if (!empty($_POST['extension_name']) && $_POST['extension_name'] !== 'None') {
        $fullName .= ' ' . $_POST['extension_name'];
    }

    // Now insert the trainee record
    $stmt = $conn->prepare("INSERT INTO trainees (
        trainee_id, full_name, program, section,
        contact_number, email, username, profile_image,
        school, status, user_id
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $school = "PAMANTASAN NG LUNGSOD NG PASIG";
    $status = "Trainee";

    $stmt->bind_param("ssssssssssi",
        $_POST['student_id'],
        $fullName,
        $_POST['program'],
        $_POST['section'],
        $_POST['contact_number'],
        $_POST['email'],
        $_POST['username'],
        $profileImage,
        $school,
        $status,
        $userId
    );

    // Execute the statement
    if (!$stmt->execute()) {
        throw new Exception("Error inserting trainee: " . $stmt->error);
    }

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Trainee added successfully'
    ]);

} catch (Exception $e) {
    // Rollback transaction if active
    if ($conn->connect_errno === 0) {
        $conn->rollback();
    }
    
    // Delete uploaded file if it exists and there was an error
    if (isset($targetPath) && file_exists($targetPath)) {
        unlink($targetPath);
    }
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// Close the connection
if (isset($stmt)) {
    $stmt->close();
}
?> 