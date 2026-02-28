<?php
require 'config.php';

try {
    // Check if column exists
    $stmt = $conn->query("DESCRIBE notifications type");
    if ($stmt->fetch()) {
        echo "Column 'type' already exists.\n";
    } else {
        $conn->exec("ALTER TABLE notifications ADD COLUMN type VARCHAR(50) NOT NULL DEFAULT 'info' AFTER message");
        echo "Column 'type' added successfully.\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
