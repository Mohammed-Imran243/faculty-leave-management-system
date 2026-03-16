<?php
require_once __DIR__ . '/../includes/config.php';

echo "Adding performance indexes to the database...\n";

try {
    // Indexes for leave_requests table
    $sql_leave = "ALTER TABLE leave_requests 
                  ADD INDEX idx_user_id (user_id),
                  ADD INDEX idx_hod_status (hod_status),
                  ADD INDEX idx_principal_status (principal_status),
                  ADD INDEX idx_start_date (start_date)";
    
    $conn->exec($sql_leave);
    echo "Successfully added indexes to leave_requests table.\n";

    // Indexes for audit_logs table
    $sql_audit = "ALTER TABLE audit_logs 
                  ADD INDEX idx_audit_user_id (user_id),
                  ADD INDEX idx_audit_created_at (created_at)";
    
    $conn->exec($sql_audit);
    echo "Successfully added indexes to audit_logs table.\n";

    echo "Optimization complete.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
