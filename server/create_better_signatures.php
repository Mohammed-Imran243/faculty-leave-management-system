<?php
require 'config.php';

$sigDir = __DIR__ . '/signatures/';
if (!is_dir($sigDir)) mkdir($sigDir, 0777, true);

function createScribble($text, $filename) {
    global $sigDir;
    $width = 200;
    $height = 60;
    $im = imagecreatetruecolor($width, $height);
    
    // Transparent background? No, PDF uses JPEG often, but PNG supports transparency.
    // Let's use white bg.
    $bg = imagecolorallocate($im, 255, 255, 255);
    $color = imagecolorallocate($im, 0, 0, 100); // Dark Blue
    imagefilledrectangle($im, 0, 0, $width-1, $height-1, $bg);
    
    // Draw a "scribble" - random bezier curves or lines
    // Simple approach: A sine wave with noise
    $prevX = 10;
    $prevY = $height / 2;
    
    for ($x = 10; $x < $width - 10; $x += 5) {
        $y = ($height / 2) + sin($x / 10) * 10 + rand(-5, 5);
        imageline($im, $prevX, $prevY, $x, $y, $color);
        $prevX = $x;
        $prevY = $y;
    }
    
    // Add text below small
    $text_color = imagecolorallocate($im, 100, 100, 100);
    imagestring($im, 1, 10, $height - 10, $text, $text_color);
    
    imagepng($im, $sigDir . $filename);
    imagedestroy($im);
    echo "Created $filename\n";
}

// 1. Principal
createScribble("(Principal Signed)", "principal.png");

// 2. HODs
try {
    $stmt = $conn->query("SELECT DISTINCT department FROM users WHERE role='hod'");
    $depts = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($depts as $dept) {
        $cleanDept = preg_replace('/[^a-zA-Z0-9]/', '_', strtolower($dept));
        createScribble("(HOD Signed)", 'hod_' . $cleanDept . '.png');
    }
} catch (Exception $e) { echo $e->getMessage(); }
?>
