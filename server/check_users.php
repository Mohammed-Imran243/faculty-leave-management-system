<?php
require_once __DIR__ . '/core/bootstrap.php';
use App\Core\Database;
use App\Services\SecurityService;

header('Content-Type: text/plain');

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    $stmt = $conn->query("SELECT id, username, email, password_hash, role FROM users WHERE username IN ('admin', 'principal')");
    $users = $stmt->fetchAll();

    foreach ($users as $user) {
        echo "User: " . $user['username'] . "\n";
        echo "Hash: " . $user['password_hash'] . "\n";
        $verify = SecurityService::verifyPassword('admin123', $user['password_hash']);
        echo "Verify 'admin123': " . ($verify ? "YES" : "NO") . "\n";
        echo "-------------------\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
