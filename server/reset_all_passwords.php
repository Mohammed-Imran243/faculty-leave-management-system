<?php
// Report all errors to catch syntax issues
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

$new_pass = '123456';
$hash = password_hash($new_pass, PASSWORD_DEFAULT);

try {
    $stmt = $conn->prepare("UPDATE users SET password_hash = ?");
    $stmt->execute([$hash]);
    echo "Success: All users password reset to '$new_pass'\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
