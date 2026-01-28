<?php
require_once 'config.php';

try {
    $stmt = $conn->prepare("SELECT id, name, username, role, department FROM users WHERE username = 'hari' OR name LIKE '%hari%'");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($users) {
        echo "Found users matching 'hari':\n";
        print_r($users);
    } else {
        echo "No user found with username 'hari' or name containing 'hari'.\n";
        // List all users to see what's there
        $stmt = $conn->query("SELECT id, username, role FROM users");
        echo "Existing users:\n";
        print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
