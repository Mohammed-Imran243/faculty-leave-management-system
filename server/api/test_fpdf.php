<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
echo "Loading FPDF...\n";
require_once '../fpdf/fpdf.php';
echo "FPDF Loaded. Creating PDF...\n";
$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial','B',16);
$pdf->Cell(40,10,'Hello World!');
$content = $pdf->Output('S');
echo "PDF Generated. Length: " . strlen($content) . "\n";
echo substr($content, 0, 10) . "...\n";
?>
