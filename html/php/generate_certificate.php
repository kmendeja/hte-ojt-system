<?php
session_start();
require_once 'db_connect.php';
require('fpdf/fpdf186/fpdf.php'); // Make sure to install FPDF library

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'trainee') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$trainee_id = $_SESSION['trainee_id'];

try {
    // Get trainee information
    $sql = "SELECT t.*, c.name as company_name, c.address as company_address,
            co.full_name as coordinator_name
            FROM trainees t
            LEFT JOIN companies c ON t.company_id = c.id
            LEFT JOIN section_assignments sa ON 
                t.program = sa.program AND 
                t.section = sa.section
            LEFT JOIN coordinators co ON sa.coordinator_id = co.coordinator_id
            WHERE t.trainee_id = ?";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $trainee_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $trainee = $result->fetch_assoc();

    if (!$trainee) {
        throw new Exception('Trainee information not found');
    }

    // Get total hours worked
    $sql = "SELECT SUM(hours_worked) as total_hours FROM attendance_logs WHERE trainee_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $trainee_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $hours = $result->fetch_assoc();
    $total_hours = $hours['total_hours'] ?? 0;

    // Check if trainee has completed requirements
    $sql = "SELECT COUNT(*) as total, 
            SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved
            FROM trainee_requirements 
            WHERE trainee_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $trainee_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $requirements = $result->fetch_assoc();

    // Check if trainee meets all criteria
    $required_hours = (strtoupper($trainee['program']) === 'BSCS') ? 162 : 486;
    if ($total_hours < $required_hours || $requirements['approved'] < $requirements['total']) {
        throw new Exception('Trainee has not met all completion requirements');
    }

    // Create PDF certificate
    $pdf = new FPDF('L', 'mm', 'A4'); // Landscape
    $pdf->AddPage();

    // Certificate content (OJT details only)
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 10, 'CERTIFICATE OF COMPLETION', 0, 1, 'C');
    $pdf->Ln(10);

    // Draw green and yellow borders
    $pdf->SetFillColor(0, 77, 36); // Dark green
    $pdf->Rect(0, 0, 297, 20, 'F');
    $pdf->Rect(0, 190, 297, 20, 'F');
    $pdf->SetFillColor(255, 204, 0); // Yellow
    $pdf->Rect(0, 18, 297, 4, 'F');

    // Add school name and header
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 10, 'This is to certify that', 0, 1, 'C');
    $pdf->Ln(5);

    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 10, $trainee['full_name'], 0, 1, 'C');
    $pdf->Ln(5);

    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 10, 'has successfully completed', 0, 1, 'C');
    $pdf->Cell(0, 10, 'On-the-Job Training Program', 0, 1, 'C');
    $pdf->Ln(5);

    $pdf->Cell(0, 10, 'at ' . $trainee['company_name'], 0, 1, 'C');
    $pdf->Cell(0, 10, $trainee['company_address'], 0, 1, 'C');
    $pdf->Ln(5);

    $pdf->Cell(0, 10, 'Total Hours Completed: ' . number_format($total_hours, 2), 0, 1, 'C');
    $pdf->Ln(10);

    $pdf->Cell(0, 10, 'Program: ' . $trainee['program'], 0, 1, 'C');
    $pdf->Cell(0, 10, 'Section: ' . $trainee['section'], 0, 1, 'C');
    $pdf->Ln(15);

    // Add signatures
    $pdf->Cell(60, 10, '_____________________', 0, 0, 'C');
    $pdf->Cell(60, 10, '_____________________', 0, 0, 'C');
    $pdf->Cell(60, 10, '_____________________', 0, 1, 'C');

    $pdf->Cell(60, 10, 'Trainee', 0, 0, 'C');
    $pdf->Cell(60, 10, 'Company Supervisor', 0, 0, 'C');
    $pdf->Cell(60, 10, 'OJT Coordinator', 0, 1, 'C');

    $pdf->Cell(60, 10, $trainee['full_name'], 0, 0, 'C');
    $pdf->Cell(60, 10, $trainee['company_name'], 0, 0, 'C');
    $pdf->Cell(60, 10, $trainee['coordinator_name'], 0, 1, 'C');

    // Generate unique filename
    $filename = 'certificate_' . $trainee_id . '_' . date('YmdHis') . '.pdf';
    $filepath = '../certificates/' . $filename;

    // Create certificates directory if it doesn't exist
    if (!file_exists('../certificates')) {
        mkdir('../certificates', 0777, true);
    }

    // Save the PDF
    $pdf->Output('F', $filepath);

    echo json_encode([
        'success' => true,
        'message' => 'Certificate generated successfully',
        'filepath' => 'certificates/' . $filename
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 