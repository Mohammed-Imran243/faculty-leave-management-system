<?php
require_once 'config.php';

echo "PHP Version: " . phpversion() . "\n";
echo "Testing Password: '123456'\n";

$genHash = password_hash('123456', PASSWORD_DEFAULT);
echo "Generated Hash (Now): $genHash\n";

$stmt = $conn->prepare("SELECT * FROM users WHERE username = 'hari'");
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    echo "User found: " . $user['username'] . " (ID: " . $user['id'] . ")\n";
    echo "Stored Hash: " . $user['password_hash'] . "\n";
    
    $check = password_verify('123456', $user['password_hash']);
    echo "Verification Result: " . ($check ? "MATCH" : "FAIL") . "\n";
} else {
    echo "User 'hari' NOT found.\n";
}
?>
