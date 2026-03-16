<?php
require_once __DIR__ . '/includes/config.php';

try {
    // Check if column exists first
    $check = $conn->query("SHOW COLUMNS FROM users LIKE 'designation'");
    if ($check->rowCount() == 0) {
        $conn->exec("ALTER TABLE users ADD COLUMN designation VARCHAR(100) DEFAULT NULL AFTER employee_code");
        echo "Successfully added designation column to users table.\n";
    } else {
        echo "Designation column already exists.\n";
    }
} catch (PDOException $e) {
    echo "Error adding column: " . $e->getMessage() . "\n";
    exit(1);
}
