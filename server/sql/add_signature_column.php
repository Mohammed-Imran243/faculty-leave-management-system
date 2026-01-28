<?php
require_once 'config.php';

try {
    // Check if column exists first
    $stmt = $conn->query("SHOW COLUMNS FROM users LIKE 'signature_path'");
    $exists = $stmt->fetch();

    if (!$exists) {
        $conn->exec("ALTER TABLE users ADD COLUMN signature_path VARCHAR(255) NULL AFTER department");
        echo "Added signature_path column to users table.\n";
    } else {
        echo "signature_path column already exists.\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
