<?php
require_once __DIR__ . '/../includes/config.php';

try {
    $conn->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS designation VARCHAR(100) DEFAULT NULL AFTER employee_code");
    echo "Successfully added designation column to users table.\n";
} catch (PDOException $e) {
    echo "Error adding column: " . $e->getMessage() . "\n";
    exit(1);
}
