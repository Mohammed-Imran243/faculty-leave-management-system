<?php
require_once 'config.php';
try {
    $sql = file_get_contents('db_fix_users.sql');
    $conn->exec($sql);
    echo "Database fixed: Added signature_path column.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
