<?php
require_once '../includes/config.php';

try {
    // 1. Make email column nullable
    $conn->exec("ALTER TABLE users MODIFY email VARCHAR(100) NULL");
    echo "Email column modified successfully.\n";

    // 2. Update Role column - transition from ENUM to VARCHAR for better flexibility with new roles
    $conn->exec("ALTER TABLE users MODIFY role VARCHAR(50) NOT NULL DEFAULT 'faculty'");
    echo "Role column modified successfully.\n";

    echo "Database update completed successfully.\n";
} catch (PDOException $e) {
    echo "Error updating database: " . $e->getMessage() . "\n";
}
?>
