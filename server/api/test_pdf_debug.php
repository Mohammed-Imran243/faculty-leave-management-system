<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Adjust path as needed based on where this file is placed.
// Assuming server/api/test_pdf_debug.php
$autoloadPath = '../vendor/autoload.php';

if (!file_exists($autoloadPath)) {
    die("Autoload file not found at: " . realpath($autoloadPath));
}

require_once $autoloadPath;

try {
    $mpdf = new \Mpdf\Mpdf();
    $mpdf->WriteHTML('<h1>Hello World from mPDF Debug</h1>');
    echo "SUCCESS: mPDF Initialized working.";
} catch (Exception $e) {
    echo "EXCEPTION: " . $e->getMessage();
} catch (Error $e) {
    echo "FATAL ERROR: " . $e->getMessage();
}
?>
