<?php
require_once 'config.php';

echo "<h1>Database V3 Update</h1>";

$file = 'db_update_v3.sql';
if (!file_exists($file)) {
    die("Error: SQL file '$file' not found.");
}

try {
    $sql = file_get_contents($file);
    // Split by semicolon vs single execution? 
    // PDO exec handles multiple statements? Sometimes depending on driver.
    // Safest is to try exec, if fails try splitting.
    // XAMPP default usually allows multiple.
    $conn->exec($sql);
    echo "<h2 style='color:green'>Success: Database updated successfully.</h2>";
    echo "<p>Added tables: notifications, leave_substitutions, approvals, audit_logs.</p>";
} catch (PDOException $e) {
    echo "<h2 style='color:red'>Error: " . $e->getMessage() . "</h2>";
}
?>
