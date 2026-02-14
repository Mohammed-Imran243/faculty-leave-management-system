<?php
/**
 * Test script - run directly in browser to see PDF generation errors.
 * Usage: /server/api/test_pdf.php?id=12
 * Remove or restrict access in production.
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');

header('Content-Type: text/plain; charset=utf-8');

try {
    require_once __DIR__ . '/../config.php';
    require_once __DIR__ . '/../fpdf/fpdf.php';
    
    echo "Config loaded.\n";
    $leaveId = isset($_GET['id']) ? (int)$_GET['id'] : 12;
    
    $stmt = $conn->prepare("SELECT l.*, u.name FROM leave_requests l JOIN users u ON l.user_id = u.id WHERE l.id = ?");
    $stmt->execute([$leaveId]);
    $leave = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$leave) {
        die("Leave #$leaveId not found.");
    }
    echo "Leave found: " . $leave['name'] . "\n";
    
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Times', '', 12);
    $pdf->Cell(0, 10, 'Test PDF - Leave #' . $leaveId, 0, 1);
    $pdf->Cell(0, 10, 'Applicant: ' . $leave['name'], 0, 1);
    
    $content = $pdf->Output('S');
    echo "PDF generated, size: " . strlen($content) . " bytes.\n";
    echo "SUCCESS - PDF generation works.\n";
    
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
    echo $e->getTraceAsString();
}
