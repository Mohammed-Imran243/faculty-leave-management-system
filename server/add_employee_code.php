<?php
require_once '../includes/config.php';

try {
    // Check if column exists first
    $stmt = $conn->query("SHOW COLUMNS FROM users LIKE 'employee_code'");
    if ($stmt->rowCount() == 0) {
        $conn->exec("ALTER TABLE users ADD COLUMN employee_code VARCHAR(50) UNIQUE AFTER username");
        echo "Column 'employee_code' added successfully.\n";
    } else {
        echo "Column 'employee_code' already exists.\n";
    }
} catch (PDOException $e) {
    echo "Error adding column: " . $e->getMessage() . "\n";
}
?>
