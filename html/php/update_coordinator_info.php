<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

// Verify coordinator session
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'coordinator') {
    echo json_encode([
        "success" => false,
        "message" => "Unauthorized: Coordinator access required."
    ]);
    exit;
}

$userId = $_SESSION['user_id'];
$response = ['success' => false, 'message' => ''];

try {
    // Handle file upload
    $profileImagePath = null;
    if (!empty($_FILES['profile_image']['name'])) {
        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/upsystem/html/php/profiles_coordinator/';
        
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        // Delete old image if exists
        $stmt = $conn->prepare("SELECT profile_picture FROM coordinators WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $oldImage = $result->fetch_assoc()['profile_picture'];
            if ($oldImage && file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . $oldImage)) {
                unlink($_SERVER['DOCUMENT_ROOT'] . '/' . $oldImage);
            }
        }

        $extension = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
        $filename = 'coordinator_' . $userId . '_' . time() . '.' . $extension;
        $targetPath = $uploadDir . $filename;

        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($_FILES['profile_image']['type'], $allowedTypes)) {
            throw new Exception('Only JPG, PNG, GIF and WebP images are allowed');
        }

        if ($_FILES['profile_image']['size'] > 2000000) {
            throw new Exception('Image size must be less than 2MB');
        }

        if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $targetPath)) {
            $profileImagePath = 'php/profiles_coordinator/' . $filename;
        } else {
            throw new Exception('Failed to move uploaded file');
        }
    }

    // Update database
    $query = "UPDATE coordinators SET 
              full_name = ?, 
              job_description = ?, 
              email = ?, 
              contact_number = ?";
    
    $params = [
        $_POST['full_name'] ?? '',
        $_POST['job_description'] ?? '',
        $_POST['email'] ?? '',
        $_POST['contact_number'] ?? ''
    ];
    
    $types = "ssss";
    
    if ($profileImagePath) {
        $query .= ", profile_picture = ?";
        $params[] = $profileImagePath;
        $types .= "s";
    }
    
    $query .= " WHERE user_id = ?";
    $params[] = $userId;
    $types .= "i";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        $response = [
            'success' => true,
            'message' => 'Profile updated successfully',
            'profile_image' => $profileImagePath ? 'http://' . $_SERVER['HTTP_HOST'] . '/' . $profileImagePath : null
        ];
    } else {
        throw new Exception("Database update failed: " . $stmt->error);
    }

} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
}

echo json_encode($response);
$conn->close();
?>