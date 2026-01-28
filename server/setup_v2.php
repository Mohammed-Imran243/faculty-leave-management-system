<?php
require_once 'config.php';

echo "<h2>System Upgrade: Phase 2</h2>";

function run_sql_file($conn, $file) {
    echo "Processing $file...<br>";
    $sql = file_get_contents($file);
    if (!$sql) {
        die("Error opening file: $file");
    }
    
    // Split by semicolon, but be careful with stored procedures (not used here yet)
    // Simple split for now
    $statements = array_filter(array_map('trim', explode(';', $sql)));

    foreach ($statements as $stmt) {
        if (!empty($stmt)) {
            try {
                $conn->exec($stmt);
                echo "<span style='color:green'>Success</span>: " . substr($stmt, 0, 50) . "...<br>";
            } catch (PDOException $e) {
                // Ignore "Column already exists" errors for idempotency
                if (strpos($e->getMessage(), "Duplicate column name") !== false) {
                     echo "<span style='color:orange'>Skipped (Exists)</span>: " . substr($stmt, 0, 50) . "...<br>";
                } else {
                     echo "<span style='color:red'>Error</span>: " . $e->getMessage() . "<br>";
                }
            }
        }
    }
}

try {
    run_sql_file($conn, 'db_schema_v2.sql');
    echo "<h3>Database Upgrade Complete!</h3>";
} catch (Exception $e) {
    echo "Critical Error: " . $e->getMessage();
}
?>
