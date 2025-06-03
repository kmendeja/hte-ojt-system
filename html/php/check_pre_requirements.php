<?php
session_start();
require_once 'db_connect.php';
header('Content-Type: application/json');

// Allow both coordinator and trainee roles
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['trainee', 'coordinator'])) {
    die(json_encode([
        'success' => false,
        'message' => 'Unauthorized: Coordinator or Trainee access required'
    ]));
}

// Get trainee_id from POST, GET, or session (for trainees)
$trainee_id = $_POST['trainee_id'] ?? $_GET['trainee_id'] ?? ($_SESSION['role'] === 'trainee' ? $_SESSION['trainee_id'] : null);

if (!$trainee_id) {
    echo json_encode(['success' => false, 'message' => 'Trainee ID required']);
    exit;
}

try {
    // Get all pre-requirements and their status
    $sql = "SELECT r.*, COALESCE(tr.id, NULL) as submission_id,
           tr.file_name, tr.file_path, 
           COALESCE(tr.status, 'Not Submitted') as status,
           COALESCE(tr.remarks, 'Not submitted yet') as remarks,
           tr.date_submitted, tr.date_updated
           FROM requirements r
           LEFT JOIN trainee_requirements tr ON 
               r.requirement_name = tr.requirement_name AND 
               tr.trainee_id = ? AND
               tr.requirement_type = r.requirement_type
           WHERE r.requirement_type = 'pre'
           ORDER BY r.position";
           
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("s", $trainee_id);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $requirements = [];
    $all_approved = true;
    $has_pending = false;
    $has_rejected = false;
    $missing_requirements = [];
    
    while ($row = $result->fetch_assoc()) {
        $requirements[] = $row;
        
        if ($row['status'] === 'Not Submitted') {
            $all_approved = false;
            $missing_requirements[] = $row['requirement_name'];
        } elseif ($row['status'] === 'Pending') {
            $all_approved = false;
            $has_pending = true;
        } elseif ($row['status'] === 'Rejected') {
            $all_approved = false;
            $has_rejected = true;
        }
    }
    
    // Get trainee's OJT status and deployment status
    $sql2 = "SELECT ojt_status, deployment_status FROM trainees WHERE trainee_id = ?";
    $stmt2 = $conn->prepare($sql2);
    if (!$stmt2) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $stmt2->bind_param("s", $trainee_id);
    if (!$stmt2->execute()) {
        throw new Exception("Execute failed: " . $stmt2->error);
    }
    $result2 = $stmt2->get_result();
    $trainee = $result2->fetch_assoc();

    echo json_encode([
        'success' => true,
        'trainee_id' => $trainee_id,
        'requirements' => $requirements,
        'all_approved' => $all_approved,
        'has_pending' => $has_pending,
        'has_rejected' => $has_rejected,
        'missing_requirements' => $missing_requirements,
        'ojt_status' => $trainee['ojt_status'],
        'deployment_status' => $trainee['deployment_status']
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    if (isset($stmt)) $stmt->close();
    if (isset($stmt2)) $stmt2->close();
    $conn->close();
}
?> 