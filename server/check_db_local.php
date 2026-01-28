<?php
// DB Connection
$host = '127.0.0.1';
$db_name = 'faculty_system';
$username = 'root';
$password = 'Imran@123'; 

try {
    $conn = new PDO("mysql:host=$host;dbname=$db_name", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connected successfully\n";
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

$tables = ['leave_substitutions', 'notifications', 'approvals', 'audit_logs'];
foreach ($tables as $t) {
    try {
        $stmt = $conn->query("SELECT 1 FROM $t LIMIT 1");
        echo "[EXISTS] $t\n";
    } catch (PDOException $e) {
        echo "[MISSING] $t\n";
    }
}
?>
