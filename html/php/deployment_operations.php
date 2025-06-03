<?php
session_start();
error_log('SESSION: ' . print_r($_SESSION, true));
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
require_once 'db_connect.php';

// Function to handle errors consistently
function sendError($message) {
    error_log("Deployment Error: " . $message);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

// Allow both coordinators and trainees
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['coordinator', 'trainee'])) {
    sendError('Unauthorized access');
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // Get companies list
        if (isset($_GET['action']) && $_GET['action'] === 'getCompanies') {
            $query = "SELECT id, name FROM companies ORDER BY name";
            $result = $conn->query($query);
            
            if (!$result) {
                throw new Exception("Database error: " . $conn->error);
            }

            $companies = [];
            while ($row = $result->fetch_assoc()) {
                $companies[] = $row;
            }

            if (empty($companies)) {
                echo json_encode(['success' => true, 'data' => [], 'message' => 'No companies found']);
            } else {
                echo json_encode(['success' => true, 'data' => $companies]);
            }
        }
        // Get specific trainee deployment
        else if (isset($_GET['trainee_id'])) {
            $trainee_id = $_GET['trainee_id'];
            // If trainee, only allow access to their own record
            if ($_SESSION['role'] === 'trainee' && $_SESSION['trainee_id'] !== $trainee_id) {
                sendError('Unauthorized access');
            }
            // If coordinator, verify access as before
            if ($_SESSION['role'] === 'coordinator') {
                $verify_sql = "SELECT 1 FROM section_assignments sa 
                              JOIN coordinators c ON sa.coordinator_id = c.coordinator_id
                              JOIN trainees t ON t.program = sa.program AND t.section = sa.section
                              WHERE c.user_id = ? AND t.trainee_id = ?";
                $verify_stmt = $conn->prepare($verify_sql);
                $verify_stmt->bind_param("is", $_SESSION['user_id'], $trainee_id);
                $verify_stmt->execute();
                if ($verify_stmt->get_result()->num_rows === 0) {
                    sendError('Unauthorized access to this trainee');
                }
            }
            
            $query = "SELECT 
                t.trainee_id,
                t.company_id,
                t.nature_of_work,
                t.deployment_date,
                t.end_date,
                t.deployment_status,
                c.name as company_name
            FROM trainees t
            LEFT JOIN companies c ON t.company_id = c.id
            WHERE t.trainee_id = ?";

            $stmt = $conn->prepare($query);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }

            $stmt->bind_param('s', $trainee_id);
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }

            $result = $stmt->get_result();
            $trainee = $result->fetch_assoc();

            if ($trainee) {
                echo json_encode(['success' => true, 'data' => $trainee]);
            } else {
                echo json_encode(['success' => true, 'data' => null, 'message' => 'No deployment found for this trainee']);
            }
        }
        // Get deployment list
        else {
            // Get coordinator's assigned sections
            $sections_sql = "SELECT DISTINCT program, section 
                           FROM section_assignments sa 
                           JOIN coordinators c ON sa.coordinator_id = c.coordinator_id 
                           WHERE c.user_id = ?";
            
            $sections_stmt = $conn->prepare($sections_sql);
            $sections_stmt->bind_param("i", $_SESSION['user_id']);
            $sections_stmt->execute();
            $sections_result = $sections_stmt->get_result();
            
            $assigned_sections = [];
            while ($row = $sections_result->fetch_assoc()) {
                $assigned_sections[] = $row;
            }
            
            if (empty($assigned_sections)) {
                echo json_encode(['success' => true, 'data' => []]);
                exit;
            }
            
            // Build the WHERE clause for assigned sections
            $where_conditions = [];
            $params = [];
            $types = "";
            
            foreach ($assigned_sections as $section) {
                $where_conditions[] = "(t.program = ? AND t.section = ?)";
                $params[] = $section['program'];
                $params[] = $section['section'];
                $types .= "ss";
            }
            
            $where_clause = implode(" OR ", $where_conditions);
            
            $query = "SELECT 
                t.trainee_id,
                t.full_name,
                t.program,
                t.section,
                t.contact_number,
                t.email,
                t.profile_image,
                c.name as company_assigned,
                t.nature_of_work,
                t.deployment_date,
                t.end_date,
                t.deployment_status,
                t.ojt_status
            FROM trainees t
            LEFT JOIN companies c ON t.company_id = c.id
            WHERE (" . $where_clause . ")
              AND (t.ojt_status IS NULL OR t.ojt_status != 'completed')
            ORDER BY t.full_name";

            $stmt = $conn->prepare($query);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            
            if (!$stmt->execute()) {
                throw new Exception($stmt->error);
            }

            $result = $stmt->get_result();
            $deployments = [];
            while ($row = $result->fetch_assoc()) {
                $deployments[] = $row;
            }

            echo json_encode(['success' => true, 'data' => $deployments]);
        }
    } catch (Exception $e) {
        sendError($e->getMessage());
    }
}

// Update deployment
else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['trainee_id'])) {
            throw new Exception('Trainee ID is required');
        }

        // Verify coordinator has access to this trainee
        $verify_sql = "SELECT 1 FROM section_assignments sa 
                      JOIN coordinators c ON sa.coordinator_id = c.coordinator_id
                      JOIN trainees t ON t.program = sa.program AND t.section = sa.section
                      WHERE c.user_id = ? AND t.trainee_id = ?";
        
        $verify_stmt = $conn->prepare($verify_sql);
        $verify_stmt->bind_param("is", $_SESSION['user_id'], $data['trainee_id']);
        $verify_stmt->execute();
        
        if ($verify_stmt->get_result()->num_rows === 0) {
            sendError('Unauthorized access to this trainee');
        }

        // Validate required fields
        $required_fields = ['company_id', 'nature_of_work', 'deployment_date', 'end_date', 'deployment_status'];
        foreach ($required_fields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Field '$field' is required");
            }
        }

        $query = "UPDATE trainees SET 
            company_id = ?,
            nature_of_work = ?,
            deployment_date = ?,
            end_date = ?,
            deployment_status = ?,
            contact_number = ?
        WHERE trainee_id = ?";

        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param(
            'issssss',
            $data['company_id'],
            $data['nature_of_work'],
            $data['deployment_date'],
            $data['end_date'],
            $data['deployment_status'],
            $data['contact_number'],
            $data['trainee_id']
        );

        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }

        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Deployment updated successfully']);
        } else {
            echo json_encode(['success' => true, 'message' => 'No changes were made to the deployment']);
        }
    } catch (Exception $e) {
        sendError($e->getMessage());
    }
}

$conn->close();
?> 