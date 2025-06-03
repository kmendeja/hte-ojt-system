<?php
session_start();
require_once 'db_connect.php';
header('Content-Type: application/json');

// Check if user is logged in as trainee
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'trainee') {
    die(json_encode([
        'success' => false,
        'message' => 'Unauthorized: Trainee access required'
    ]));
}

try {
    $trainee_id = $_SESSION['trainee_id'];
    
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
           ORDER BY r.id";
           
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
    
    // Get trainee's OJT status
    $sql = "SELECT ojt_status FROM trainees WHERE trainee_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $trainee_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $trainee = $result->fetch_assoc();
    
    // Get trainee's deployment status
    $sql = "SELECT deployment_status FROM trainees WHERE trainee_id = ?";
    $stmt2 = $conn->prepare($sql);
    $stmt2->bind_param("s", $trainee_id);
    $stmt2->execute();
    $result2 = $stmt2->get_result();
    $trainee2 = $result2->fetch_assoc();
    $deployment_status = $trainee2['deployment_status'] ?? 'not-deployed';
    
    $restricted = !$all_approved;
    $restriction_message = '';
    
    if ($restricted) {
        if (!empty($missing_requirements)) {
            $restriction_message = "You cannot access this feature because you have not submitted the following requirements: " . 
                                 implode(", ", $missing_requirements);
        } elseif ($has_pending) {
            $restriction_message = "You cannot access this feature because some of your requirements are still pending approval.";
        } elseif ($has_rejected) {
            $restriction_message = "You cannot access this feature because some of your requirements have been rejected. Please resubmit them.";
        }
    }
    
    // Restrict if not deployed
    if ($all_approved && $deployment_status !== 'deployed') {
        $restricted = true;
        $restriction_message = "You cannot access this feature until you are deployed to a company. Please wait for your OJT coordinator to assign you to a company.";
    }
    
    echo json_encode([
        'success' => true,
        'restricted' => $restricted,
        'restriction_message' => $restriction_message,
        'requirements' => $requirements,
        'all_approved' => $all_approved,
        'has_pending' => $has_pending,
        'has_rejected' => $has_rejected,
        'missing_requirements' => $missing_requirements,
        'ojt_status' => $trainee['ojt_status'] ?? 'pending',
        'deployment_status' => $deployment_status
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