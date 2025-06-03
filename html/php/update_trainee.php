<?php
require_once 'db_connect.php';

header('Content-Type: application/json');

try {
    // Get trainee ID from POST data
    if (!isset($_POST['trainee_id'])) {
        throw new Exception('Trainee ID is required');
    }

    $trainee_id = $_POST['trainee_id'];
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

    // Prepare full name
    $full_name = trim($_POST['last_name']);
    if (!empty($_POST['first_name'])) {
        $full_name .= ', ' . trim($_POST['first_name']);
    }
    if (!empty($_POST['middle_name'])) {
        $full_name .= ' ' . trim($_POST['middle_name']);
    }
    if (!empty($_POST['extension_name']) && $_POST['extension_name'] !== 'None') {
        $full_name .= ' ' . trim($_POST['extension_name']);
    }

    // Start transaction
    $conn->begin_transaction();

    try {
        // Update trainee info
        if ($profile_image_path) {
            $query = "UPDATE trainees SET 
                trainee_id = ?,
                program = ?,
                section = ?,
                full_name = ?,
                email = ?,
                username = ?,
                contact_number = ?,
                profile_image = ?
                WHERE trainee_id = ?";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param(
                "sssssssss",
                $_POST['student_id'],
                $_POST['program'],
                $_POST['section'],
                $full_name,
                $_POST['email'],
                $_POST['username'],
                $_POST['contact_number'],
                $profile_image_path,
                $trainee_id
            );
        } else {
            $query = "UPDATE trainees SET 
                trainee_id = ?,
                program = ?,
                section = ?,
                full_name = ?,
                email = ?,
                username = ?,
                contact_number = ?
                WHERE trainee_id = ?";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param(
                "ssssssss",
                $_POST['student_id'],
                $_POST['program'],
                $_POST['section'],
                $full_name,
                $_POST['email'],
                $_POST['username'],
                $_POST['contact_number'],
                $trainee_id
            );
        }

        $stmt->execute();

        if ($stmt->affected_rows === 0) {
            throw new Exception('No trainee was updated. Please check if the trainee exists.');
        }

        $conn->commit();

        echo json_encode([
            "success" => true,
            "message" => "Trainee updated successfully"
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