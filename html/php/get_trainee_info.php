<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json'); // <== Important: tell browser we send JSON

try {
    if (!isset($_SESSION['trainee_id'])) {
        echo json_encode([
            "success" => false,
            "message" => "Unauthorized: Session trainee_id not set."
        ]);
        exit;
    }
    
    $trainee_id = $_SESSION['trainee_id'];

    if ($conn->connect_error) {
        echo json_encode([
            "success" => false,
            "message" => "Database connection failed: " . $conn->connect_error
        ]);
        exit;
    }

    $stmt = $conn->prepare(
        "SELECT t.trainee_id, t.full_name, t.program, t.section, t.status, t.school, t.contact_number, t.email, t.profile_image, c.name AS company_name
         FROM trainees t
         LEFT JOIN companies c ON t.company_id = c.id
         WHERE t.trainee_id = ?"
    );
    $stmt->bind_param("s", $trainee_id);
    $stmt->execute();
    $profile = $stmt->get_result()->fetch_assoc();

    if (!$profile) {
        echo json_encode([
            "success" => false,
            "message" => "Trainee not found for ID: $trainee_id"
        ]);
        exit;
    }

    // Add debugging log
    error_log("Profile data: " . print_r($profile, true));

    // ✅ Proper JSON output
    echo json_encode([
        "success" => true,
        "profile" => $profile
    ]);

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}
?>
