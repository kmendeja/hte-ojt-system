<?php
ob_clean(); // Clear any previous output buffer
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('Asia/Manila');

session_start();
header('Content-Type: application/json');

// Include database connection
require_once 'db_connect.php';

// Only POST allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Read JSON
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';
$traineeId = $input['trainee_id'] ?? '';

// Validate session and trainee ID
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    echo json_encode(['success' => false, 'message' => 'Session expired or invalid']);
    exit;
}

// For trainees, ensure they can only access their own records
if ($_SESSION['role'] === 'trainee' && $_SESSION['trainee_id'] !== $traineeId) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// For coordinators, validate they have access to this trainee
if ($_SESSION['role'] === 'coordinator') {
    if (!isset($_SESSION['coordinator_id'])) {
        echo json_encode(['success' => false, 'message' => 'Coordinator session not set']);
        exit;
    }
    $coordinator_id = $_SESSION['coordinator_id'];
    $sql = "SELECT 1 FROM section_assignments sa 
            JOIN trainees t ON t.program = sa.program 
            AND t.section = sa.section 
            WHERE sa.coordinator_id = ? AND t.trainee_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $coordinator_id, $traineeId);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized access to trainee records']);
        exit;
    }
}

// Validate input
if (!$action || !$traineeId) {
    echo json_encode(['success' => false, 'message' => 'Missing action or trainee_id']);
    exit;
}

// Check pre-requirements status
function checkPreRequirements($traineeId) {
    global $conn;
    
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
    
    $stmt->bind_param("s", $traineeId);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $all_approved = true;
    
    while ($row = $result->fetch_assoc()) {
        if ($row['status'] === 'Not Submitted' || $row['status'] === 'Pending' || $row['status'] === 'Rejected') {
            $all_approved = false;
            break;
        }
    }
    
    $stmt->close();
    return $all_approved;
}

// Add this helper function near the top (after checkPreRequirements)
function getRequiredHours($traineeId) {
    global $conn;
    $sql = "SELECT program FROM trainees WHERE trainee_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $traineeId);
    $stmt->execute();
    $result = $stmt->get_result();
    $trainee = $result->fetch_assoc();
    $stmt->close();
    return (strtoupper($trainee['program']) === 'BSCS') ? 162 : 486;
}

function hasCompletedOJT($traineeId) {
    global $conn;
    $requiredHours = getRequiredHours($traineeId);
    $sql = "SELECT SUM(hours_worked) as total_hours FROM attendance_logs WHERE trainee_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $traineeId);
    $stmt->execute();
    $result = $stmt->get_result();
    $hours = $result->fetch_assoc();
    $stmt->close();
    $totalHours = $hours['total_hours'] ?? 0;
    return $totalHours >= $requiredHours;
}

// Add this helper function after hasCompletedOJT
function updateOjtStatusIfCompleted($traineeId) {
    global $conn;
    $requiredHours = getRequiredHours($traineeId);
    $sql = "SELECT SUM(hours_worked) as total_hours FROM attendance_logs WHERE trainee_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $traineeId);
    $stmt->execute();
    $result = $stmt->get_result();
    $hours = $result->fetch_assoc();
    $stmt->close();
    $totalHours = $hours['total_hours'] ?? 0;
    if ($totalHours >= $requiredHours) {
        $sql = "UPDATE trainees SET ojt_status = 'completed' WHERE trainee_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $traineeId);
        $stmt->execute();
        $stmt->close();
    }
}

// Add this function to check and update ojt_status for any trainee who has reached the required hours
function ensureOjtStatusUpToDate($traineeId) {
    global $conn;
    $sql = "SELECT ojt_status FROM trainees WHERE trainee_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $traineeId);
    $stmt->execute();
    $result = $stmt->get_result();
    $trainee = $result->fetch_assoc();
    $stmt->close();
    if ($trainee && $trainee['ojt_status'] !== 'completed') {
        updateOjtStatusIfCompleted($traineeId);
    }
}

// Add this helper function after checkPreRequirements
function isWeekend() {
    $dayOfWeek = date('N'); // 1 (Monday) to 7 (Sunday)
    return $dayOfWeek >= 6; // 6 is Saturday, 7 is Sunday
}

// Action switch
switch ($action) {
    case 'timeIn':  handleTimeIn($traineeId);  break;
    case 'timeOut': handleTimeOut($traineeId); break;
    case 'getLogs': handleGetLogs($traineeId); break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

// Time In function
function handleTimeIn($traineeId) {
    global $conn;
    
    // Check if it's weekend
    if (isWeekend()) {
        echo json_encode([
            'success' => false,
            'message' => 'Time In not allowed on weekends. Weekends are not working days.'
        ]);
        return;
    }
    
    // Check pre-requirements first
    try {
        if (!checkPreRequirements($traineeId)) {
            echo json_encode([
                'success' => false,
                'message' => 'Cannot start OJT: Pre-requirements not yet approved'
            ]);
            return;
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error checking pre-requirements: ' . $e->getMessage()
        ]);
        return;
    }

    // Check company deployment status
    $sql = "SELECT deployment_status, company_id FROM trainees WHERE trainee_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $traineeId);
    $stmt->execute();
    $result = $stmt->get_result();
    $trainee = $result->fetch_assoc();
    
    if (!$trainee || !$trainee['company_id'] || $trainee['deployment_status'] !== 'deployed') {
        echo json_encode([
            'success' => false,
            'message' => 'Cannot start OJT: You must be deployed to a company first'
        ]);
        return;
    }
    
    if (hasCompletedOJT($traineeId)) {
        echo json_encode(['success' => false, 'message' => 'You have already completed your required OJT hours.']);
        return;
    }
    
    $today       = date('Y-m-d');
    $currentTime = date('H:i:s');

    // Check if the trainee has already Time In today
    $sql = "SELECT * FROM `attendance_logs` WHERE `trainee_id` = ? AND `date` = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode([
            'success' => false,
            'message' => 'Prepare failed (SELECT): ' . $conn->errno . ' – ' . $conn->error
        ]);
        exit;
    }
    $stmt->bind_param("ss", $traineeId, $today);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Already Time In today']);
    } else {
        // Otherwise, record the Time In
        $sql = "INSERT INTO `attendance_logs` (`trainee_id`, `date`, `time_in`) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            echo json_encode([
                'success' => false,
                'message' => 'Prepare failed (INSERT): ' . $conn->errno . ' – ' . $conn->error
            ]);
            exit;
        }
        $stmt->bind_param("sss", $traineeId, $today, $currentTime);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Time In recorded', 'time_in' => $currentTime]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Execute failed (INSERT): ' . $stmt->error
            ]);
        }
    }

    $stmt->close();
}

// Time Out function
function handleTimeOut($traineeId) {
    global $conn;
    
    // Check if it's weekend
    if (isWeekend()) {
        echo json_encode([
            'success' => false,
            'message' => 'Time Out not allowed on weekends. Weekends are not working days.'
        ]);
        return;
    }
    
    // Check pre-requirements first
    try {
        if (!checkPreRequirements($traineeId)) {
            echo json_encode([
                'success' => false,
                'message' => 'Cannot continue OJT: Pre-requirements not yet approved'
            ]);
            return;
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error checking pre-requirements: ' . $e->getMessage()
        ]);
        return;
    }
    
    if (hasCompletedOJT($traineeId)) {
        echo json_encode(['success' => false, 'message' => 'You have already completed your required OJT hours.']);
        return;
    }
    
    $today       = date('Y-m-d');
    $currentTime = date('H:i:s');

    // Check if Time In exists for today
    $sql = "SELECT `time_in`, `time_out` FROM `attendance_logs` WHERE `trainee_id` = ? AND `date` = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode([
            'success' => false,
            'message' => 'Prepare failed (SELECT time_in): ' . $conn->errno . ' – ' . $conn->error
        ]);
        exit;
    }
    $stmt->bind_param("ss", $traineeId, $today);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'No Time In found for today']);
    } else if (empty($row['time_in'])) {
        echo json_encode(['success' => false, 'message' => 'Cannot Time Out without Time In']);
    } else if (!empty($row['time_out'])) {
        echo json_encode(['success' => false, 'message' => 'Already Time Out today']);
    } else {
        // Calculate hours worked and update the record
        $hoursWorked = calculateHoursWorked($row['time_in'], $currentTime);

        $sql = "UPDATE `attendance_logs` SET `time_out` = ?, `hours_worked` = ? WHERE `trainee_id` = ? AND `date` = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            echo json_encode([
                'success' => false,
                'message' => 'Prepare failed (UPDATE): ' . $conn->errno . ' – ' . $conn->error
            ]);
            exit;
        }
        $stmt->bind_param("sdss", $currentTime, $hoursWorked, $traineeId, $today);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Time Out recorded', 'time_out' => $currentTime]);
            updateOjtStatusIfCompleted($traineeId);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Execute failed (UPDATE): ' . $stmt->error
            ]);
        }
    }

    $stmt->close();
}

// Get Logs function
function handleGetLogs($traineeId) {
    global $conn;
    ensureOjtStatusUpToDate($traineeId);
    // Prepare the query to fetch the logs for the given trainee_id, ordering by date
    $sql = "SELECT `id`, `trainee_id`, `date`, `time_in`, `time_out`, `hours_worked` 
            FROM `attendance_logs` 
            WHERE `trainee_id` = ? 
            ORDER BY `date` DESC";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        echo json_encode([
            'success' => false,
            'message' => 'Prepare failed (SELECT logs): ' . $conn->errno . ' – ' . $conn->error
        ]);
        exit;
    }

    // Bind the trainee_id parameter
    $stmt->bind_param("s", $traineeId);

    // Execute the query
    if (!$stmt->execute()) {
        echo json_encode([
            'success' => false,
            'message' => 'Execute failed (SELECT logs): ' . $stmt->error
        ]);
        exit;
    }

    // Get the result of the query
    $result = $stmt->get_result();
    if ($result === false) {
        echo json_encode(['success' => false, 'message' => 'Failed to retrieve logs']);
        exit;
    }

    $logs = [];
    while ($row = $result->fetch_assoc()) {
	$logs[] = [
        'id'           => $row['id'],
        'trainee_id'   => $row['trainee_id'],
        'date'         => $row['date'],
        'time_in'      => !empty($row['time_in']) ? $row['time_in'] : '',
        'time_out'     => !empty($row['time_out']) ? $row['time_out'] : '',
        'hours_worked' => isset($row['hours_worked']) ? number_format($row['hours_worked'], 2) : ''
    ];
}

    echo json_encode([
        'success' => true,
        'data'    => $logs
    ]);

    $stmt->close();
}

function calculateHoursWorked($timeIn, $timeOut) {
    $timeInObj  = new DateTime($timeIn);
    $timeOutObj = new DateTime($timeOut);
    $interval   = $timeInObj->diff($timeOutObj);
    
    $hours = $interval->h + ($interval->i / 60);
    return round($hours, 2);
}