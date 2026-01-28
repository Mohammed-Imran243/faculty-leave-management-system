<?php
require_once 'config.php';
require_once 'audit.php';

// Test Logging
logAudit($conn, 1, 'TEST_ACTION', ['info'=>'Verifying audit system']);

// Verify
$stmt = $conn->query("SELECT * FROM audit_logs ORDER BY id DESC LIMIT 5");
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Audit Logs Check:\n";
foreach($logs as $log) {
    echo "[{$log['created_at']}] User:{$log['user_id']} | Action:{$log['action']} | Details:{$log['details']} | IP:{$log['ip_address']}\n";
}

if (count($logs) > 0) {
    echo "\nSUCCESS: Audit logs are working.\n";
} else {
    echo "\nFAILURE: No logs found.\n";
}
?>
