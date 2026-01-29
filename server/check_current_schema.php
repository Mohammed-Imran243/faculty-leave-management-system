<?php
require_once 'config.php';

// Suppress warnings
error_reporting(E_ERROR | E_PARSE);

try {
    $stmt = $conn->query("SHOW COLUMNS FROM users LIKE 'username'");
    $username_col = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "V4_STATUS: " . ($username_col ? "INSTALLED" : "MISSING") . "\n";

    $stmt = $conn->query("SHOW TABLES LIKE 'leave_substitutions'");
    $v3_table = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "V3_STATUS: " . ($v3_table ? "INSTALLED" : "MISSING") . "\n";

} catch (PDOException $e) {
    echo "DB_ERROR: " . $e->getMessage() . "\n";
}
?>
