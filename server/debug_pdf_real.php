<?php
require_once 'SimpleJWT.php';
require_once 'config.php';

// 1. Login to get Token
$ch = curl_init('http://localhost/faculty-system/server/api/auth.php/login');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['username' => 'admin', 'password' => 'admin123']));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$res = curl_exec($ch);
$login = json_decode($res, true);
curl_close($ch);

if (!isset($login['token'])) {
    die("Login Failed: " . $res);
}

$token = $login['token'];
echo "Got Token.\n";

// 2. Try to Download PDF (ID=1, adjust if needed)
// Find a valid leave ID first?
$stmt = $conn->query("SELECT id FROM leave_requests LIMIT 1");
$leaveId = $stmt->fetchColumn();
if (!$leaveId) die("No leaves found to test PDF.");

echo "Testing PDF for Leave ID: $leaveId\n";

$url = "http://localhost/faculty-system/server/api/generate_pdf.php?id=$leaveId";
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token"]);
$pdfContent = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Content Length: " . strlen($pdfContent) . "\n";
echo "First 100 bytes:\n" . substr($pdfContent, 0, 100) . "\n";

if (strpos($pdfContent, '%PDF') === 0) {
    echo "SUCCESS: It is a PDF.\n";
} else {
    echo "FAILURE: Not a PDF.\n";
}
?>
