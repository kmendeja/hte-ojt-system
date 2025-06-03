<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

try {
    // Verify admin session
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        echo json_encode([
            "success" => false,
            "message" => "Unauthorized: Admin access required."
        ]);
        exit;
    }

    $admin_id = $_SESSION['user_id'];
    $profile_image_path = null;

    // Handle profile image upload if provided
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/upsystem/html/php/profiles_admin/';
        
        // Create directory if it doesn't exist
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        // Delete old profile image if it exists
        $stmt = $conn->prepare("SELECT profile_image FROM admins WHERE user_id = ?");
        $stmt->bind_param("i", $admin_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $oldImage = $result->fetch_assoc()['profile_image'];
        
        if ($oldImage && file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . $oldImage)) {
            unlink($_SERVER['DOCUMENT_ROOT'] . '/' . $oldImage);
        }

        // Generate unique filename
        $extension = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
        $filename = 'admin_' . $admin_id . '.' . $extension;
        $destination = $uploadDir . $filename;

        // Validate image
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($_FILES['profile_image']['type'], $allowed_types)) {
            throw new Exception('Only JPG, PNG, GIF and WebP images are allowed');
        }

        if ($_FILES['profile_image']['size'] > 2000000) {
            throw new Exception('Image size must be less than 2MB');
        }

        // Move uploaded file
        if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $destination)) {
            $profile_image_path = 'php/profiles_admin/' . $filename;
        } else {
            throw new Exception('Failed to upload profile image');
        }
    }

    // Update admin info in the database
    $conn->begin_transaction();

    try {
        $admin_stmt = $conn->prepare("
            UPDATE admins 
            SET full_name = ?, position = ?, email = ?, contact_number = ?" . 
            ($profile_image_path ? ", profile_image = ?" : "") . " 
            WHERE user_id = ?
        ");

        if ($profile_image_path) {
            $admin_stmt->bind_param(
                "sssssi", 
                $_POST['full_name'],
                $_POST['position'],
                $_POST['email'],
                $_POST['contact_number'],
                $profile_image_path,
                $admin_id
            );
        } else {
            $admin_stmt->bind_param(
                "ssssi", 
                $_POST['full_name'],
                $_POST['position'],
                $_POST['email'],
                $_POST['contact_number'],
                $admin_id
            );
        }

        $admin_stmt->execute();
        $conn->commit();

        // Return the full URL if image was updated
        $return_image = $profile_image_path ? 'http://' . $_SERVER['HTTP_HOST'] . '/' . $profile_image_path : null;
        
        echo json_encode([
            "success" => true,
            "message" => "Profile updated successfully",
            "profile_image" => $return_image
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}
?>