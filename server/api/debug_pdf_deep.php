<?php
// Force text content type to see errors
header('Content-Type: text/plain');
ini_set('display_errors', 1);
error_reporting(E_ALL);

chdir(__DIR__);
echo "Debug Start\n";

if (!file_exists('../vendor/autoload.php')) {
    echo "Vendor Autoload Missing!\n";
    if (file_exists('../../vendor/autoload.php')) echo "Found at ../../vendor/autoload.php\n";
} else {
    echo "Vendor Autoload Found.\n";
    require_once '../vendor/autoload.php';
}

if (!class_exists('\Mpdf\Mpdf')) {
    die("Error: mPDF Class NOT Found.\n");
}
echo "mPDF Class Found.\n";

try {
    $mpdf = new \Mpdf\Mpdf(['format' => 'A4']);
    echo "mPDF Instantiated Successfully.\n";
    
    $mpdf->WriteHTML('<h1>Test PDF</h1>');
    echo "WriteHTML Successful.\n";
    
    // Attempt output buffer capture instead of stream
    $content = $mpdf->Output('', 'S'); // S = Return as string
    echo "PDF Output Generated (Size: " . strlen($content) . " bytes)\n";
    if (strlen($content) < 100) {
        echo "WARNING: PDF seems too small!\n";
    }
    
    echo "Tests Passed. If you see this transaction log, the library is working.\n";

} catch (Throwable $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
?>
