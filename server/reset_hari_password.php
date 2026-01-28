<?php
require_once 'config.php';

$username = 'hari';
$new_pass = '123456';
$hash = password_hash($new_pass, PASSWORD_DEFAULT);

try {
    $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE username = ?");
    $stmt->execute([$hash, $username]);
    echo "Password for '$username' reset to '$new_pass'";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
