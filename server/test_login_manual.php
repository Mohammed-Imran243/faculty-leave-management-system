<?php
require_once 'config.php';

$username = 'requester';
$password = '123';

echo "Testing login for user: $username\n";

$stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo "User not found!\n";
    exit;
}

echo "User found: " . $user['username'] . "\n";
echo "Hash in DB: " . $user['password_hash'] . "\n";

if (password_verify($password, $user['password_hash'])) {
    echo "SUCCESS: Password matches hash.\n";
} else {
    echo "FAILURE: Password does NOT match hash.\n";
    echo "Re-hashing '123' gives: " . password_hash('123', PASSWORD_DEFAULT) . "\n";
}
?>
