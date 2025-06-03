<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db_connect.php';

// Function to check for duplicate company
function checkDuplicateCompany($data, $excludeId = null) {
    global $conn;
    
    $sql = "SELECT COUNT(*) as count FROM companies WHERE name = ?";
    $params = [$data['name']];
    $types = "s";
    
    if ($excludeId !== null) {
        $sql .= " AND id != ?";
        $params[] = $excludeId;
        $types .= "i";
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row['count'] > 0;
}

// Function to add a new company
function addCompany($data) {
    global $conn;
    
    // Check for duplicates
    if (checkDuplicateCompany($data)) {
        return ['success' => false, 'message' => 'Operation failed: Duplicate company detected. A company with the same name already exists in the system.'];
    }
    
    $sql = "INSERT INTO companies (
        name,
        effective_date,
        address,
        corporate_email,
        corporate_contact,
        contact_person,
        position,
        contact_number,
        email
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "sssssssss",
        $data['name'],
        $data['effective_date'],
        $data['address'],
        $data['corporate_email'],
        $data['corporate_contact'],
        $data['contact_person'],
        $data['position'],
        $data['contact_number'],
        $data['email']
    );
    
    if ($stmt->execute()) {
        return ['success' => true, 'message' => 'Company added successfully'];
    } else {
        return ['success' => false, 'message' => 'Error adding company: ' . $conn->error];
    }
}

// Function to get all companies
function getAllCompanies() {
    global $conn;
    
    $sql = "SELECT * FROM companies ORDER BY name ASC";
    $result = $conn->query($sql);
    
    $companies = [];
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $companies[] = $row;
        }
    }
    
    return $companies;
}

// Function to update a company
function updateCompany($id, $data) {
    global $conn;
    
    // Check for duplicates, excluding current company
    if (checkDuplicateCompany($data, $id)) {
        return ['success' => false, 'message' => 'Operation failed: Duplicate company detected. A company with the same name already exists in the system.'];
    }
    
    $sql = "UPDATE companies SET 
        name = ?,
        effective_date = ?,
        logo = ?,
        address = ?,
        corporate_email = ?,
        corporate_contact = ?,
        contact_person = ?,
        position = ?,
        contact_number = ?,
        email = ?,
        documents = ?
        WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "sssssssssssi",
        $data['name'],
        $data['effective_date'],
        $data['logo'],
        $data['address'],
        $data['corporate_email'],
        $data['corporate_contact'],
        $data['contact_person'],
        $data['position'],
        $data['contact_number'],
        $data['email'],
        $data['documents'],
        $id
    );
    
    if ($stmt->execute()) {
        return ['success' => true, 'message' => 'Company updated successfully'];
    } else {
        return ['success' => false, 'message' => 'Error updating company: ' . $conn->error];
    }
}

// Function to delete a company
function deleteCompany($id) {
    global $conn;
    
    $sql = "DELETE FROM companies WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        return ['success' => true, 'message' => 'Company deleted successfully'];
    } else {
        return ['success' => false, 'message' => 'Error deleting company: ' . $conn->error];
    }
}

// Handle file uploads
function handleFileUpload($file, $type) {
    // Always store the logo path as a web-accessible relative path (php/uploads/filename.ext)
    $target_dir = "uploads/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    $file_extension = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    $new_filename = uniqid() . '.' . $file_extension;
    $target_file = $target_dir . $new_filename;
    $relative_path = 'php/uploads/' . $new_filename; // This is what is stored in the DB and used in <img src="...">
    // Check if file is an actual image
    if ($type === 'logo') {
        $check = getimagesize($file["tmp_name"]);
        if($check === false) {
            return ['success' => false, 'message' => 'File is not an image.'];
        }
    }
    // Check file size (5MB max)
    if ($file["size"] > 5000000) {
        return ['success' => false, 'message' => 'File is too large.'];
    }
    // Allow certain file formats
    $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx'];
    if (!in_array($file_extension, $allowed_types)) {
        return ['success' => false, 'message' => 'Only JPG, JPEG, PNG, GIF, WebP, PDF, DOC & DOCX files are allowed.'];
    }
    if (move_uploaded_file($file["tmp_name"], $target_file)) {
        return ['success' => true, 'file_path' => $relative_path];
    } else {
        return ['success' => false, 'message' => 'Error uploading file.'];
    }
}

// Function to get companies for export (only those with deployed trainees for this coordinator)
function getCoordinatorCompaniesForExport($coordinator_id) {
    global $conn;
    // Get coordinator's assigned sections
    $sections_sql = "SELECT DISTINCT sa.program, sa.section 
                   FROM section_assignments sa 
                   JOIN coordinators c ON sa.coordinator_id = c.coordinator_id 
                   WHERE c.user_id = ?";
    $sections_stmt = $conn->prepare($sections_sql);
    $sections_stmt->bind_param("i", $coordinator_id);
    $sections_stmt->execute();
    $sections_result = $sections_stmt->get_result();

    $sections = [];
    while ($row = $sections_result->fetch_assoc()) {
        $sections[] = $row;
    }

    if (empty($sections)) {
        return [];
    }

    // Build the query to get companies where trainees from coordinator's sections are deployed
    $sql = "SELECT DISTINCT c.* FROM companies c 
           JOIN trainees t ON c.id = t.company_id 
           WHERE 1=1";

    $section_conditions = [];
    $params = [];
    $types = "";

    foreach ($sections as $section) {
        $section_conditions[] = "(t.program = ? AND t.section = ?)";
        $params[] = $section['program'];
        $params[] = $section['section'];
        $types .= "ss";
    }

    if (!empty($section_conditions)) {
        $sql .= " AND (" . implode(" OR ", $section_conditions) . ")";
    }

    $sql .= " ORDER BY c.name ASC";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $companies = [];

    while ($row = $result->fetch_assoc()) {
        $companies[] = $row;
    }

    return $companies;
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    $response = ['success' => false, 'message' => 'Invalid action'];
    
    switch($action) {
        case 'getCompanies':
            $searchTerm = $_GET['search'] ?? '';
            $sortOrder = $_GET['sort'] ?? '';
            
            // Get coordinator's assigned sections
            $coordinator_id = $_SESSION['user_id'];
            $sections_sql = "SELECT DISTINCT sa.program, sa.section 
                           FROM section_assignments sa 
                           JOIN coordinators c ON sa.coordinator_id = c.coordinator_id 
                           WHERE c.user_id = ?";
            $sections_stmt = $conn->prepare($sections_sql);
            $sections_stmt->bind_param("i", $coordinator_id);
            $sections_stmt->execute();
            $sections_result = $sections_stmt->get_result();
            
            $sections = [];
            while ($row = $sections_result->fetch_assoc()) {
                $sections[] = $row;
            }
            
            // If no sections assigned, return empty result
            if (empty($sections)) {
                echo json_encode(['status' => 'success', 'data' => []]);
                exit;
            }
            
            // Build the query to get companies where trainees from coordinator's sections are deployed
            $sql = "SELECT DISTINCT c.* FROM companies c 
                   JOIN trainees t ON c.id = t.company_id 
                   WHERE 1=1";
            
            // Add section conditions
            $section_conditions = [];
            $params = [];
            $types = "";
            
            foreach ($sections as $section) {
                $section_conditions[] = "(t.program = ? AND t.section = ?)";
                $params[] = $section['program'];
                $params[] = $section['section'];
                $types .= "ss";
            }
            
            if (!empty($section_conditions)) {
                $sql .= " AND (" . implode(" OR ", $section_conditions) . ")";
            }
            
            // Add search conditions
            if (!empty($searchTerm)) {
                $searchTerm = "%$searchTerm%";
                $sql .= " AND (c.name LIKE ? OR c.address LIKE ? OR c.corporate_email LIKE ? OR c.contact_person LIKE ?)";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $types .= "ssss";
            }
            
            // Add sorting
            switch ($sortOrder) {
                case 'ascending':
                    $sql .= " ORDER BY c.name ASC";
                    break;
                case 'descending':
                    $sql .= " ORDER BY c.name DESC";
                    break;
                default:
                    $sql .= " ORDER BY c.created_at DESC";
            }
            
            $stmt = $conn->prepare($sql);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            $companies = [];
            
            while ($row = $result->fetch_assoc()) {
                $companies[] = $row;
            }
            
            echo json_encode(['status' => 'success', 'data' => $companies]);
            exit;
            
        case 'generateReport':
            $coordinator_id = $_SESSION['user_id'];
            $companies = getCoordinatorCompaniesForExport($coordinator_id);
            echo json_encode(['status' => 'success', 'data' => $companies]);
            exit;
            
        case 'add':
            $data = [
                'name' => $_POST['name'],
                'effective_date' => $_POST['effective_date'],
                'logo' => '',
                'address' => $_POST['address'],
                'corporate_email' => $_POST['corporate_email'],
                'corporate_contact' => $_POST['corporate_contact'],
                'contact_person' => $_POST['contact_person'],
                'position' => $_POST['position'],
                'contact_number' => $_POST['contact_number'],
                'email' => $_POST['email'],
                'documents' => ''
            ];
            
            // Handle logo upload
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] === 0) {
                $logo_result = handleFileUpload($_FILES['logo'], 'logo');
                if ($logo_result['success']) {
                    $data['logo'] = $logo_result['file_path'];
                } else {
                    $response = $logo_result;
                    break;
                }
            }
            
            // Handle document upload
            if (isset($_FILES['documents']) && $_FILES['documents']['error'] === 0) {
                $doc_result = handleFileUpload($_FILES['documents'], 'document');
                if ($doc_result['success']) {
                    $data['documents'] = $doc_result['file_path'];
                } else {
                    $response = $doc_result;
                    break;
                }
            }
            
            $response = addCompany($data);
            break;
            
        case 'update':
            $id = $_POST['id'];
            $data = [
                'name' => $_POST['name'],
                'effective_date' => $_POST['effective_date'],
                'logo' => $_POST['current_logo'],
                'address' => $_POST['address'],
                'corporate_email' => $_POST['corporate_email'],
                'corporate_contact' => $_POST['corporate_contact'],
                'contact_person' => $_POST['contact_person'],
                'position' => $_POST['position'],
                'contact_number' => $_POST['contact_number'],
                'email' => $_POST['email'],
                'documents' => $_POST['current_documents']
            ];
            
            // Handle logo upload
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] === 0) {
                $logo_result = handleFileUpload($_FILES['logo'], 'logo');
                if ($logo_result['success']) {
                    $data['logo'] = $logo_result['file_path'];
                } else {
                    $response = $logo_result;
                    break;
                }
            }
            
            // Handle document upload
            if (isset($_FILES['documents']) && $_FILES['documents']['error'] === 0) {
                $doc_result = handleFileUpload($_FILES['documents'], 'document');
                if ($doc_result['success']) {
                    $data['documents'] = $doc_result['file_path'];
                } else {
                    $response = $doc_result;
                    break;
                }
            }
            
            $response = updateCompany($id, $data);
            break;
            
        case 'delete':
            $id = $_POST['id'];
            $response = deleteCompany($id);
            break;
            
        case 'list':
            $search = isset($_POST['search']) ? $_POST['search'] : '';
            $sort = isset($_POST['sort']) ? $_POST['sort'] : 'name';
            $order = isset($_POST['order']) ? $_POST['order'] : 'ASC';
            $allowedSort = ['name','effective_date','address','corporate_email','corporate_contact','contact_person','position','contact_number','email'];
            $allowedOrder = ['ASC','DESC'];
            $sort = in_array($sort, $allowedSort) ? $sort : 'name';
            $order = in_array(strtoupper($order), $allowedOrder) ? strtoupper($order) : 'ASC';
            $sql = "SELECT * FROM companies WHERE 1";
            if ($search !== '') {
                $search = $conn->real_escape_string($search);
                $sql .= " AND (name LIKE '%$search%' OR address LIKE '%$search%' OR corporate_email LIKE '%$search%' OR contact_person LIKE '%$search%' OR position LIKE '%$search%' OR contact_number LIKE '%$search%' OR email LIKE '%$search%')";
            }
            $sql .= " ORDER BY $sort $order";
            $result = $conn->query($sql);
            $companies = [];
            if ($result && $result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    $companies[] = $row;
                }
            }
            $response = ['success' => true, 'data' => $companies];
            break;
        case 'upload_csv':
            // CSV upload logic removed. Please use upload_company_csv.php for CSV uploads.
            $response = ['success' => false, 'message' => 'CSV upload is now handled separately.'];
            break;
        case 'get':
            $id = $_POST['id'];
            $sql = "SELECT * FROM companies WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    $company = $result->fetch_assoc();
                    $response = ['success' => true, 'data' => $company];
                } else {
                    $response = ['success' => false, 'message' => 'Company not found'];
                }
            } else {
                $response = ['success' => false, 'message' => 'Error fetching company: ' . $conn->error];
            }
            break;
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}
?> 