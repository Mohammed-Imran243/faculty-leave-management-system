<?php
require_once 'config.php';

echo "=== Final System Check ===\n";

// 1. DB Connect
try {
    $conn->query("SELECT 1");
    echo "[PASS] Database Connection\n";
} catch (Exception $e) {
    echo "[FAIL] Database Connection: " . $e->getMessage() . "\n";
    exit;
}

// 2. Check Tables
$tables = ['users', 'leave_requests', 'approvals', 'leave_substitutions', 'audit_logs'];
foreach ($tables as $t) {
    $res = $conn->query("SHOW TABLES LIKE '$t'")->rowCount();
    if ($res) echo "[PASS] Table Exists: $t\n";
    else echo "[FAIL] Table Missing: $t\n";
}

// 3. User Check
$users = $conn->query("SELECT role, COUNT(*) as c FROM users GROUP BY role")->fetchAll(PDO::FETCH_ASSOC);
echo "--- User Counts ---\n";
foreach($users as $u) {
    echo "Role: {$u['role']} = {$u['c']}\n";
}

// 4. Leave Check
$leaves = $conn->query("SELECT COUNT(*) FROM leave_requests")->fetchColumn();
echo "[INFO] Total Leaves: $leaves\n";

// 5. Signature Check
$sigs = $conn->query("SELECT COUNT(*) FROM users WHERE signature_path IS NOT NULL")->fetchColumn();
echo "[INFO] Users with Signatures: $sigs\n";

echo "=== Check Complete ===\n";
?>
