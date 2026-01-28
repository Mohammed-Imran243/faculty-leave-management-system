<?php
require_once 'config.php';

echo "<h2>Database Update Tool</h2>";

try {
    // 1. Check if 'duration_type' exists
    $stmt = $conn->query("SHOW COLUMNS FROM leave_requests LIKE 'duration_type'");
    if ($stmt->rowCount() == 0) {
        $conn->exec("ALTER TABLE leave_requests ADD COLUMN duration_type ENUM('Days', 'Hours') DEFAULT 'Days'");
        echo "<p style='color:green'>Success: Added column 'duration_type'</p>";
    } else {
        echo "<p style='color:orange'>Skipped: Column 'duration_type' already exists</p>";
    }

    // 2. Check if 'selected_hours' exists
    $stmt = $conn->query("SHOW COLUMNS FROM leave_requests LIKE 'selected_hours'");
    if ($stmt->rowCount() == 0) {
        $conn->exec("ALTER TABLE leave_requests ADD COLUMN selected_hours VARCHAR(50) DEFAULT NULL");
        echo "<p style='color:green'>Success: Added column 'selected_hours'</p>";
    } else {
        echo "<p style='color:orange'>Skipped: Column 'selected_hours' already exists</p>";
    }

    echo "<h3>Update Complete! You can now use the app.</h3>";
    echo "<a href='../client/dashboard.html'>Go to Dashboard</a>";

} catch (PDOException $e) {
    echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
}
?>
