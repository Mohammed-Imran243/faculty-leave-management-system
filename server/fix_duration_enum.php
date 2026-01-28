<?php
// Fix Duration Enum
$host = 'localhost';
$db_name = 'faculty_system';
$username = 'root';
$password = 'Imran@123'; 

try {
    $conn = new PDO("mysql:host=$host;dbname=$db_name", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Modifying duration_type ENUM...\n";
    
    // 1. Expand ENUM to include both sets
    $conn->exec("ALTER TABLE leave_requests MODIFY COLUMN duration_type ENUM('Days', 'Hours', 'Full', 'Hourly') DEFAULT 'Days'");
    echo "Expanded ENUM.\n";

    // 2. Migrate Data
    $conn->exec("UPDATE leave_requests SET duration_type='Days' WHERE duration_type='Full'");
    $conn->exec("UPDATE leave_requests SET duration_type='Hours' WHERE duration_type='Hourly'");
    echo "Migrated Data.\n";

    // 3. Restrict ENUM to target set
    $conn->exec("ALTER TABLE leave_requests MODIFY COLUMN duration_type ENUM('Days', 'Hours') DEFAULT 'Days'");
    echo "Restricted ENUM to Days/Hours.\n";

    echo "Done.\n";

} catch(PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
