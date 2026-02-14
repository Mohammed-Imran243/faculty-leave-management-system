<?php
/**
 * Application configuration.
 * Copy to config.local.php and set values there (or use env) for production.
 */
$isWeb = isset($_SERVER['REQUEST_METHOD']);

if ($isWeb) {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        exit(0);
    }
}

$host     = '127.0.0.1';
$db_name  = 'faculty_system';
$username = 'root';
$password = '';
$base_url = '';
if (file_exists(__DIR__ . '/config.local.php')) {
    require __DIR__ . '/config.local.php';
}
if ($base_url === '' && $isWeb && isset($_SERVER['HTTP_HOST'])) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $base_url = $scheme . '://' . $_SERVER['HTTP_HOST'];
}

try {
    $conn = new PDO("mysql:host=$host;dbname=$db_name", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    if ($isWeb) {
        header('Content-Type: application/json');
        echo json_encode(["error" => "Connection failed"]);
    } else {
        echo "Connection failed: " . $e->getMessage();
    }
    exit(1);
}


