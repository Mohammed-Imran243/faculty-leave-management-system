<?php
// Mock Signature Upload for HOD (ID: 2 typically)
require_once 'config.php';
$hodId = 2; // Adjust based on your HOD user
$signatureFile = 'test_signature.png';

// Create a dummy image
$im = @imagecreate(100, 50) or die("Cannot Initialize new GD image stream");
$background_color = imagecolorallocate($im, 255, 255, 255);
$text_color = imagecolorallocate($im, 0, 0, 0);
imagestring($im, 1, 5, 5,  "Verified HOD", $text_color);
imagepng($im, "../uploads/signatures/signature_$hodId.png");
imagedestroy($im);

// Update DB
$dbPath = "uploads/signatures/signature_$hodId.png";
$stmt = $conn->prepare("UPDATE users SET signature_path = ? WHERE id = ?");
$stmt->execute([$dbPath, $hodId]);

echo "Mock Signature Uploaded for User $hodId (Path: $dbPath)\n";
?>
