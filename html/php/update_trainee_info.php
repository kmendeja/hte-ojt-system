<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

try {
    // Check session and role
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'trainee') {
        echo json_encode([
            "success" => false,
            "message" => "Unauthorized: Trainee access required."
        ]);
        exit;
    }

    $trainee_id = $_SESSION['trainee_id'];
    $profile_image_path = null;

    // Handle profile image upload if provided
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/upsystem/html/php/profiles_trainee/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        // Delete old profile image if it exists
        $stmt = $conn->prepare("SELECT profile_image FROM trainees WHERE trainee_id = ?");
        $stmt->bind_param("s", $trainee_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $oldImage = $result->fetch_assoc()['profile_image'];
        if ($oldImage && file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . $oldImage)) {
            unlink($_SERVER['DOCUMENT_ROOT'] . '/' . $oldImage);
        }

        // Generate unique filename
        $extension = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
        $filename = 'trainee_' . $trainee_id . '.' . $extension;
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
            $profile_image_path = 'php/profiles_trainee/' . $filename;
        } else {
            throw new Exception('Failed to upload profile image');
        }
    }

    // Update trainee info in the database
    $conn->begin_transaction();

    try {
        // Update trainee info
        $stmt = $conn->prepare("
            UPDATE trainees 
            SET full_name = ?,
                email = ?,
                contact_number = ?,
                program = ?,
                section = ?,
                school = ?,
                status = ?
                " . ($profile_image_path ? ", profile_image = ?" : "") . "
            WHERE trainee_id = ?
        ");

        if ($profile_image_path) {
            $stmt->bind_param(
                "sssssssss",
                $_POST['full_name'],
                $_POST['email'],
                $_POST['contact_number'],
                $_POST['program'],
                $_POST['section'],
                $_POST['school'],
                $_POST['status'],
                $profile_image_path,
                $trainee_id
            );
        } else {
            $stmt->bind_param(
                "ssssssss",
                $_POST['full_name'],
                $_POST['email'],
                $_POST['contact_number'],
                $_POST['program'],
                $_POST['section'],
                $_POST['school'],
                $_POST['status'],
                $trainee_id
            );
        }

        $stmt->execute();
        $conn->commit();

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