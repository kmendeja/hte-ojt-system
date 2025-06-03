<?php
session_start();
require_once 'db_connect.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    // Validate required fields
    $required_fields = ['employeeId', 'lastName', 'firstName', 'email', 'contactNumber', 'username', 'password'];
    $missing_fields = [];
    
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            $missing_fields[] = $field;
        }
    }
    
    if (!empty($missing_fields)) {
        throw new Exception('Missing required fields: ' . implode(', ', $missing_fields));
    }

    // Start transaction
    $conn->autocommit(FALSE);

    // Get form data
    $employeeId = trim($_POST['employeeId']);
    $lastName = trim($_POST['lastName']);
    $firstName = trim($_POST['firstName']);
    $middleName = isset($_POST['middleName']) ? trim($_POST['middleName']) : '';
    $extensionName = isset($_POST['extensionName']) ? trim($_POST['extensionName']) : '';
    $email = trim($_POST['email']);
    $contactNumber = trim($_POST['contactNumber']);
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }

    // Check if email/username already exists
    $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();

    if ($count > 0) {
        throw new Exception('Email already exists');
    }

    // Construct full name
    $fullName = strtoupper($lastName . ', ' . $firstName . ' ' . $middleName);
    if (!empty($extensionName)) {
        $fullName .= ' ' . $extensionName;
    }

    // Insert into users table
    $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'coordinator')");
    $stmt->bind_param("ss", $email, $password);
    $stmt->execute();
    $userId = $conn->insert_id;

    // Handle profile picture upload
    $profilePicture = null;
    if (isset($_FILES['profilePic']) && $_FILES['profilePic']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'profiles_coordinator/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileExtension = strtolower(pathinfo($_FILES['profilePic']['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (!in_array($fileExtension, $allowedExtensions)) {
            throw new Exception('Invalid file type. Allowed types: ' . implode(', ', $allowedExtensions));
        }

        $newFileName = 'coordinator_' . $userId . '_' . time() . '.' . $fileExtension;
        $uploadFile = $uploadDir . $newFileName;

        if (move_uploaded_file($_FILES['profilePic']['tmp_name'], $uploadFile)) {
            $profilePicture = 'php/' . $uploadFile;
        }
    }

    // Insert into coordinators table
    $stmt = $conn->prepare("INSERT INTO coordinators (user_id, full_name, employee_id, contact_number, email, profile_picture, account_created) VALUES (?, ?, ?, ?, ?, ?, CURDATE())");
    $stmt->bind_param("isssss", $userId, $fullName, $employeeId, $contactNumber, $email, $profilePicture);
    $stmt->execute();

    // Commit transaction
    $conn->commit();
    $conn->autocommit(TRUE);

    echo json_encode(['success' => true, 'message' => 'Coordinator added successfully']);

} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($conn)) {
        $conn->rollback();
        $conn->autocommit(TRUE);
    }
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

if (isset($conn)) {
    $conn->close();
}
?> 