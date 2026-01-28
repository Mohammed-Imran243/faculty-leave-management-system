<?php
require_once 'config.php';
$u = 'hari';
$p = '123456';
$h = password_hash($p, PASSWORD_DEFAULT);
$stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE username = ?");
$stmt->execute([$h, $u]);
echo "Reset $u to $p\n";
?>
