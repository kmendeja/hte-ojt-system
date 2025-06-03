<?php
// Prevent any output before headers
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Clear any previous output
if (ob_get_level()) ob_end_clean();

session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

// Debug log
error_log("Starting get_trainee_requirements.php");
error_log("Session data: " . print_r($_SESSION, true));

// Check if user is logged in as trainee or coordinator
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['trainee', 'coordinator'])) {
    die(json_encode([
        'success' => false,
        'message' => 'Unauthorized: Trainee or Coordinator access required.'
    ]));
}

try {
    // If coordinator is accessing
    if ($_SESSION['role'] === 'coordinator') {
        error_log("Processing coordinator request for user_id: " . $_SESSION['user_id']);
        
        $trainee_id = $_GET['trainee_id'] ?? '';
        if (empty($trainee_id)) {
            // Get all trainees first
            $sql = "SELECT DISTINCT t.* 
                    FROM trainees t
                    INNER JOIN section_assignments sa ON 
                        t.program = sa.program AND 
                        t.section = sa.section
                    INNER JOIN coordinators c ON 
                        sa.coordinator_id = c.coordinator_id
                    WHERE c.user_id = ?
                    ORDER BY t.trainee_id";
            
            error_log("Executing main trainee query");
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            
            $stmt->bind_param("i", $_SESSION['user_id']);
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            
            $result = $stmt->get_result();
            error_log("Query returned " . $result->num_rows . " trainees");
            
            $trainees = [];
            while ($row = $result->fetch_assoc()) {
                // Get pre-requirements for this trainee
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
                       WHERE r.requirement_type = ?
                       ORDER BY r.position";
                
                $req_stmt = $conn->prepare($sql);
                if (!$req_stmt) {
                    throw new Exception("Prepare requirements query failed: " . $conn->error);
                }
                
                // Get pre-requirements
                $type = 'pre';
                $req_stmt->bind_param("ss", $row['trainee_id'], $type);
                $req_stmt->execute();
                $req_result = $req_stmt->get_result();
                $row['pre_requirements'] = [];
                while ($req = $req_result->fetch_assoc()) {
                    $row['pre_requirements'][] = $req;
                }
                
                // Get post-requirements
                $type = 'post';
                $req_stmt->bind_param("ss", $row['trainee_id'], $type);
                $req_stmt->execute();
                $req_result = $req_stmt->get_result();
                $row['post_requirements'] = [];
                while ($req = $req_result->fetch_assoc()) {
                    $row['post_requirements'][] = $req;
                }
                
                error_log("Processed trainee: " . $row['trainee_id']);
                error_log("Pre-requirements count: " . count($row['pre_requirements']));
                error_log("Post-requirements count: " . count($row['post_requirements']));
                
                $trainees[] = $row;
                $req_stmt->close();
            }
            
            $response = [
                'success' => true,
                'data' => $trainees
            ];
            
            error_log("Sending response with " . count($trainees) . " trainees");
            die(json_encode($response));
        }
    } else {
        $trainee_id = $_SESSION['trainee_id'];
    }

    $type = $_GET['type'] ?? ''; // 'pre' or 'post'

    if (!in_array($type, ['pre', 'post'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid requirement type'
        ]);
        exit;
    }

    error_log("Retrieving requirements for trainee: " . $trainee_id . ", type: " . $type);

    // Get submissions for the specified type
    $sql = "SELECT r.requirement_name, r.requirement_type, r.position, tr.file_name, tr.file_path, tr.status, tr.remarks, tr.date_submitted, tr.date_updated
            FROM requirements r
            LEFT JOIN trainee_requirements tr ON r.requirement_name = tr.requirement_name AND r.requirement_type = tr.requirement_type AND tr.trainee_id = ?
            WHERE r.requirement_type = ?
            ORDER BY r.position";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $trainee_id, $type);
    $stmt->execute();
    $result = $stmt->get_result();
    
    error_log("Query executed. Found " . $result->num_rows . " submissions");
    
    $submissions = [];
    while ($row = $result->fetch_assoc()) {
        error_log("Processing submission for requirement: " . $row['requirement_name']);
        error_log("File path: " . $row['file_path']);
        
        // Fix file path checking - use absolute path from workspace root
        $full_path = dirname(dirname(__FILE__)) . '/' . $row['file_path'];
        error_log("Checking file at absolute path: " . $full_path);
        
        if (!file_exists($full_path)) {
            error_log("Warning: File does not exist at path: " . $full_path);
        }
        
        // Format the submission data
        $submissions[$row['requirement_name'] . '||' . $row['requirement_type']] = [
            'requirement_name' => $row['requirement_name'],
            'requirement_type' => $row['requirement_type'],
            'status' => $row['status'],
            'remarks' => $row['remarks'],
            'file' => [
                'original_name' => $row['file_name'],
                'path' => $row['file_path']
            ],
            'date_submitted' => $row['date_submitted'],
            'date_updated' => $row['date_updated']
        ];
    }
    
    error_log("Returning " . count($submissions) . " formatted submissions");
    
    echo json_encode([
        'success' => true,
        'submissions' => $submissions
    ]);

} catch (Exception $e) {
    error_log("Error in get_trainee_requirements.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    die(json_encode([
        'success' => false,
        'message' => 'Error retrieving submissions: ' . $e->getMessage()
    ]));
} finally {
    if (isset($stmt)) $stmt->close();
    if (isset($conn)) $conn->close();
}
?> 