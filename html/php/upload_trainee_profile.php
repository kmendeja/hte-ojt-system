<?php
require_once 'db_connect.php';

header('Content-Type: application/json');

try {
    if (!isset($_FILES['profile_image'])) {
        throw new Exception('No file uploaded');
    }

    if (!isset($_POST['trainee_id'])) {
        throw new Exception('Trainee ID is required');
    }

    $file = $_FILES['profile_image'];
    $traineeId = $_POST['trainee_id'];
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('File upload failed');
    }

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
    $filename = $traineeId . '_' . time() . '.' . $extension;
    $targetPath = $uploadDir . $filename;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new Exception('Failed to save the uploaded file');
    }

    // Update database with new profile image path
    $stmt = $pdo->prepare("UPDATE trainees SET profile_image = ? WHERE trainee_id = ?");
    $relativePath = 'profiles_trainee/' . $filename;
    if (!$stmt->execute([$relativePath, $traineeId])) {
        // If database update fails, delete the uploaded file
        unlink($targetPath);
        throw new Exception('Failed to update profile image in database');
    }

    echo json_encode([
        'success' => true,
        'message' => 'Profile image uploaded successfully',
        'profile_image' => $relativePath
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 