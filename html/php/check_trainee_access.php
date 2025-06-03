<?php
// Immediate logging to verify file inclusion
error_log("=== check_trainee_access.php START ===");
error_log("Request URI: " . $_SERVER['REQUEST_URI']);
error_log("Script Name: " . $_SERVER['SCRIPT_NAME']);
error_log("PHP Self: " . $_SERVER['PHP_SELF']);

if (php_sapi_name() !== 'cli') {
    if (session_status() === PHP_SESSION_NONE) session_start();
    require_once 'db_connect.php';
    
    error_log("Session started and DB connected");
    error_log("Session data: " . print_r($_SESSION, true));

    // If this is an API call (expects JSON), do not redirect, just return JSON
    if (
        isset($_SERVER['HTTP_ACCEPT']) && 
        strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false
    ) {
        error_log("This is an API call (JSON)");
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'trainee') {
            error_log("Unauthorized API call");
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }
    } else {
        error_log("This is a normal page load");
        // For normal page loads, redirect as before
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'trainee') {
            error_log("Unauthorized page access - redirecting to login");
            header('Location: ../login.html');
            exit;
        }
    }
}

// Debug: Log when this file is included and which page is being checked
error_log('check_trainee_access.php included on page: ' . (
    isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : 'unknown')
);

// Debug: Log session info
error_log('Session data: ' . print_r($_SESSION, true));

// Function to check if trainee has access
function checkTraineeAccess() {
    global $conn;
    
    $trainee_id = $_SESSION['trainee_id'];
    error_log("Checking trainee access for ID: " . $trainee_id);
    
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
        error_log("Failed to prepare statement for checkTraineeAccess");
        return false;
    }
    
    $stmt->bind_param("s", $trainee_id);
    if (!$stmt->execute()) {
        error_log("Failed to execute statement for checkTraineeAccess");
        return false;
    }
    
    $result = $stmt->get_result();
    $all_approved = true;
    
    while ($row = $result->fetch_assoc()) {
        if ($row['status'] === 'Not Submitted' || $row['status'] === 'Pending' || $row['status'] === 'Rejected') {
            $all_approved = false;
            error_log("Requirement not approved: " . $row['requirement_name'] . " - Status: " . $row['status']);
            break;
        }
    }
    
    $stmt->close();
    error_log("Trainee access check result: " . ($all_approved ? "Approved" : "Not Approved"));
    if (!$all_approved) return false;

    // Check deployment status
    $sql = "SELECT deployment_status FROM trainees WHERE trainee_id = ?";
    $stmt2 = $conn->prepare($sql);
    $stmt2->bind_param("s", $trainee_id);
    $stmt2->execute();
    $result2 = $stmt2->get_result();
    $trainee2 = $result2->fetch_assoc();
    $stmt2->close();
    if ($trainee2['deployment_status'] !== 'deployed') {
        error_log("Trainee not deployed. Access denied.");
        return false;
    }
    return true;
}

function isCertificateEligible($trainee_id, $conn) {
    error_log("Eligibility: Starting check for trainee_id={$trainee_id}");
    
    // Check OJT status
    $sql = "SELECT ojt_status, program FROM trainees WHERE trainee_id = ?";
    error_log("Eligibility: SQL1: $sql");
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $trainee_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $trainee = $result->fetch_assoc();
    $stmt->close();
    error_log("Eligibility: SQL1 result: " . print_r($trainee, true));
    
    if (!$trainee || $trainee['ojt_status'] !== 'completed') {
        error_log("Eligibility: OJT status not completed. Status: " . ($trainee['ojt_status'] ?? 'not set'));
        return false;
    }
    
    $required_hours = (strtoupper($trainee['program']) === 'BSCS') ? 162 : 486;
    error_log("Eligibility: Required hours for program {$trainee['program']}: {$required_hours}");
    
    // Check hours
    $sql = "SELECT SUM(hours_worked) as total_hours FROM attendance_logs WHERE trainee_id = ?";
    error_log("Eligibility: SQL2: $sql");
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $trainee_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $hours = $result->fetch_assoc();
    $stmt->close();
    error_log("Eligibility: SQL2 result: " . print_r($hours, true));
    
    $total_hours = $hours['total_hours'] ?? 0;
    error_log("Eligibility: total_hours={$total_hours} required_hours={$required_hours}");
    
    if ($total_hours < $required_hours) {
        error_log("Eligibility: Not enough hours. Current: {$total_hours}, Required: {$required_hours}");
        return false;
    }
    
    // Check requirements
    $sql = "SELECT COUNT(*) as total FROM requirements WHERE requirement_type IN ('pre', 'post')";
    error_log("Eligibility: SQL3: $sql");
    $result = $conn->query($sql);
    $reqs = $result->fetch_assoc();
    $total_requirements = $reqs['total'];
    error_log("Eligibility: SQL3 result: " . print_r($reqs, true));
    
    $sql = "SELECT COUNT(*) as approved FROM trainee_requirements WHERE trainee_id = ? AND status = 'Approved'";
    error_log("Eligibility: SQL4: $sql");
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $trainee_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $approved = $result->fetch_assoc();
    $approved_count = $approved['approved'];
    $stmt->close();
    error_log("Eligibility: SQL4 result: " . print_r($approved, true));
    
    error_log("Eligibility: total_requirements={$total_requirements} approved_count={$approved_count}");
    
    if (!($total_requirements > 0 && $approved_count == $total_requirements)) {
        error_log("Eligibility: Not all requirements approved. Total: {$total_requirements}, Approved: {$approved_count}");
        return false;
    }
    
    error_log("Eligibility: PASSED - All checks completed successfully");
    return true;
}

// Get current page
$current_page = basename($_SERVER['PHP_SELF']);
$is_requirements_page = strpos($current_page, 'requirements') !== false;

// Always check certificate eligibility first (except on certificate page)
if ($current_page !== 'trainee_certificate.html' && isset($_SESSION['trainee_id'])) {
    error_log("Checking certificate eligibility for page: " . $current_page);
    $eligible = isCertificateEligible($_SESSION['trainee_id'], $conn);
    error_log('Certificate eligibility check result: ' . ($eligible ? 'yes' : 'no'));
    
    if ($eligible) {
        error_log("Trainee is eligible for certificate. Redirecting...");
        if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
            echo json_encode(['redirect' => '/upsystem/html/trainee_certificate.html']);
            exit;
        } else {
            header('Location: /upsystem/html/trainee_certificate.html');
            exit;
        }
    }
}

// Now check requirements (for all other cases)
if (!$is_requirements_page && !checkTraineeAccess()) {
    error_log("Requirements not met. Redirecting to requirements page.");
    header('Location: /upsystem/html/trainee_requirements.html');
    exit;
}
?> 