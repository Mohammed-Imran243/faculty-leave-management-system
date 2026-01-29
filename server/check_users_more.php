<?php
require_once 'config.php';
try {
    error_reporting(E_ERROR);
    $stmt = $conn->query("SHOW COLUMNS FROM users LIKE 'signature_path'");
    $col = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "signature_path: " . ($col ? "EXISTS" : "MISSING") . "\n";
} catch (PDOException $e) {
    echo "DB Error: " . $e->getMessage();
}
?>
