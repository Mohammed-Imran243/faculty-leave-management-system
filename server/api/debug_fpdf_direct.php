<?php
// Define ID for testing
$_GET['id'] = 8;
$_SERVER['REQUEST_METHOD'] = 'GET';

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../SimpleJWT.php';

// Manually include FPDF to see if IT crashes
require_once __DIR__ . '/../fpdf/fpdf.php';
echo "FPDF Included OK.\n";

$pdf = new FPDF();
echo "FPDF Instantiated OK.\n";
$pdf->AddPage();
$pdf->SetFont('Arial','B',16);
$pdf->Cell(40,10,'Hello World!');
$content = $pdf->Output('S');
echo "PDF Generated OK (Size: " . strlen($content) . ")\n";

// Check the DrawColor / Rect functions used in generate_pdf
$pdf->SetDrawColor(0,128,0);
$pdf->Rect(10, 10, 60, 25);
echo "Rect OK.\n";
?>
