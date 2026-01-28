<?php
require_once 'config.php';

$username = 'hari';
$password = '123456';

echo "Checking credentials for User: $username / Pass: $password\n";

try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo "User not found!\n";
    } else {
        echo "User Found: ID=" . $user['id'] . "\n";
        echo "Stored Hash: " . $user['password_hash'] . "\n";
        
        if (password_verify($password, $user['password_hash'])) {
            echo "SUCCESS: Password matches hash.\n";
        } else {
            echo "FAILURE: Password does NOT match hash.\n";
            // Generate what it should be
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            echo "Expected Hash (newly generated): $newHash\n";
        }
    }

} catch (PDOException $e) {
    echo "DB Error: " . $e->getMessage() . "\n";
}
?>
