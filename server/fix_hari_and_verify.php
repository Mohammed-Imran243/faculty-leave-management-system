<?php
require_once 'config.php';
$p = '123456';
$h = password_hash($p, PASSWORD_DEFAULT);
$stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE username = 'hari'");
$stmt->execute([$h]);

// Verify
$stmt = $conn->query("SELECT * FROM users WHERE username = 'hari'");
$u = $stmt->fetch();
if (password_verify($p, $u['password_hash'])) {
    echo "HARI PASSWORD FIXED to 123456 (Verified)\n";
} else {
    echo "FAILED TO FIX PASSWORD\n";
}
?>
