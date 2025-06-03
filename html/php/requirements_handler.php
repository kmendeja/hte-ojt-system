<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

// Include database connection
require_once 'db_connect.php';

// Get the action from the request
$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'get':
        // Get requirements with optional search
        $search = isset($_GET['search']) ? $_GET['search'] : '';
        
        $sql = "SELECT * FROM requirements WHERE 1=1";
        
        if (!empty($search)) {
            $search = $conn->real_escape_string($search);
            $sql .= " AND requirement_name LIKE '%$search%'";
        }
        
        // Order by type and position
        $sql .= " ORDER BY requirement_type ASC, position ASC";
        
        $result = $conn->query($sql);
        $requirements = [];
        
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $requirements[] = $row;
            }
        }
        
        echo json_encode(['success' => true, 'data' => $requirements]);
        break;

    case 'add':
        // Add new requirement
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['requirement_name']) || !isset($data['requirement_type'])) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            break;
        }
        
        $name = $conn->real_escape_string($data['requirement_name']);
        $type = $conn->real_escape_string($data['requirement_type']);
        
        // Get the next position for this type
        $sql = "SELECT MAX(position) as max_pos FROM requirements WHERE requirement_type = '$type'";
        $result = $conn->query($sql);
        $row = $result->fetch_assoc();
        $next_position = ($row['max_pos'] ?? 0) + 1;
        
        $sql = "INSERT INTO requirements (requirement_name, requirement_type, position) VALUES ('$name', '$type', $next_position)";
        
        if ($conn->query($sql)) {
            echo json_encode(['success' => true, 'message' => 'Requirement added successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error adding requirement: ' . $conn->error]);
        }
        break;

    case 'edit':
        // Edit existing requirement
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['id']) || !isset($data['requirement_name']) || !isset($data['requirement_type'])) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            break;
        }
        
        $id = (int)$data['id'];
        $name = $conn->real_escape_string($data['requirement_name']);
        $type = $conn->real_escape_string($data['requirement_type']);
        
        // Get old values for syncing
        $res = $conn->query("SELECT requirement_name, requirement_type FROM requirements WHERE id = $id");
        $old = $res ? $res->fetch_assoc() : null;
        $old_name = $old ? $conn->real_escape_string($old['requirement_name']) : '';
        $old_type = $old ? $conn->real_escape_string($old['requirement_type']) : '';
        
        // Update requirement
        $sql = "UPDATE requirements SET requirement_name = '$name', requirement_type = '$type' WHERE id = $id";
        
        if ($conn->query($sql)) {
            // SYNC: Update trainee_requirements
            if ($old_name !== '' && $old_type !== '') {
                $conn->query("UPDATE trainee_requirements SET requirement_name = '$name', requirement_type = '$type' WHERE requirement_name = '$old_name' AND requirement_type = '$old_type'");
            }
            echo json_encode(['success' => true, 'message' => 'Requirement updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error updating requirement: ' . $conn->error]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
?> 