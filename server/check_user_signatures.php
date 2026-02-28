<?php
require 'config.php';
try {
    $stmt = $conn->query("SELECT id, name, role, signature_path FROM users WHERE role IN ('hod', 'principal')");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($users as $u) {
        echo "User {$u['id']} ({$u['role']}): {$u['signature_path']}\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
