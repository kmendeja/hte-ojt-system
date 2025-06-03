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
    $required_fields = ['employeeId', 'lastName', 'firstName', 'email', 'contactNumber', 'username'];
    $missing_fields = [];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            $missing_fields[] = $field;
        }
    }
    if (!empty($missing_fields)) {
        throw new Exception('Missing required fields: ' . implode(', ', $missing_fields));
    }

    // Get form data
    $employeeId = trim($_POST['employeeId']);
    $lastName = trim($_POST['lastName']);
    $firstName = trim($_POST['firstName']);
    $middleName = isset($_POST['middleName']) ? trim($_POST['middleName']) : '';
    $extensionName = isset($_POST['extensionName']) ? trim($_POST['extensionName']) : '';
    $email = trim($_POST['email']);
    $contactNumber = trim($_POST['contactNumber']);
    $username = trim($_POST['username']);
    $userId = isset($_POST['userId']) ? intval($_POST['userId']) : 0;
    $jobDescription = isset($_POST['jobDescription']) ? trim($_POST['jobDescription']) : '';
    if ($userId <= 0) throw new Exception('Invalid user ID.');

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }

    // Construct full name
    $fullName = strtoupper($lastName . ', ' . $firstName . ' ' . $middleName);
    if (!empty($extensionName)) {
        $fullName .= ' ' . $extensionName;
    }

    // Start transaction
    $conn->autocommit(FALSE);

    // Update users table (username/email)
    $stmt = $conn->prepare("UPDATE users SET username = ? WHERE user_id = ?");
    $stmt->bind_param("si", $username, $userId);
    $stmt->execute();

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

    // Update coordinators table
    $query = "UPDATE coordinators SET full_name=?, employee_id=?, contact_number=?, email=?, job_description=?";
    $params = [$fullName, $employeeId, $contactNumber, $email, $jobDescription];
    $types = "sssss";
    if ($profilePicture) {
        $query .= ", profile_picture=?";
        $params[] = $profilePicture;
        $types .= "s";
    }
    $query .= " WHERE user_id=?";
    $params[] = $userId;
    $types .= "i";
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();

    $conn->commit();
    $conn->autocommit(TRUE);
    echo json_encode(['success' => true, 'message' => 'Coordinator information updated successfully.']);
} catch (Exception $e) {
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