<?php
// Mock Environment
$_GET['id'] = 8; // From previous output
$_SERVER['REQUEST_METHOD'] = 'GET';

// Mock Auth (Bypass JWT for this CLI test)
// But generate_pdf.php checks JWT. 
// I will copy generate_pdf.php to generate_pdf_test.php and comment out auth for debugging.
$content = file_get_contents('api/generate_pdf.php');
$content = str_replace('if (!$user) {', 'if (false) { // Mocked', $content);
$content = str_replace('$user = $token', '$user = ["id"=>1, "role"=>"admin"]', $content);

// Also remove ob_end_clean to see errors!
$content = str_replace('ob_end_clean();', '//ob_end_clean();', $content);
$content = str_replace('error_reporting(0);', 'error_reporting(E_ALL);', $content);
$content = str_replace("ini_set('display_errors', 0);", "ini_set('display_errors', 1);", $content);

// Fix require paths (since we run from server root, but api is inside)
// Actually I'll save this test script IN api folder to match paths.

file_put_contents('api/generate_pdf_debug_crash.php', $content);

echo "Created crash debugger at api/generate_pdf_debug_crash.php\n";
?>
