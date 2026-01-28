<?php
require_once 'config.php';

try {
    $stmt = $conn->query("SELECT id, name, username, email FROM users");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "--- Users List ---\n";
    foreach($users as $u) {
        echo "ID: " . $u['id'] . " | Name: " . $u['name'] . " | User: " . $u['username'] . " | Email: " . $u['email'] . "\n";
    }
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
