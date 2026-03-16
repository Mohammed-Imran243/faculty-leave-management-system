<?php
require_once __DIR__ . '/../includes/config.php';
$hash = password_hash('pass123', PASSWORD_BCRYPT);
$conn->exec("UPDATE users SET password = '$hash' WHERE username IN ('hari', 'hod', 'principal')");
echo "Passwords reset successfully.\n";
