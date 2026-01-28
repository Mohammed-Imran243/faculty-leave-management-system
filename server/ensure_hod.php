<?php
require_once 'config.php';

$username = 'hod';
$password = 'hod123';
$email = 'hod@college.edu';
$name = 'Head of Dept';
$role = 'hod';
$dept = 'Computer Science';

$hash = password_hash($password, PASSWORD_DEFAULT);

try {
    // Check if exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        echo "User 'hod' already exists. Updating password to 'hod123'...\n";
        $update = $conn->prepare("UPDATE users SET password_hash = ? WHERE username = ?");
        $update->execute([$hash, $username]);
    } else {
        echo "Creating user 'hod'...\n";
        $insert = $conn->prepare("INSERT INTO users (name, username, email, password_hash, role, department) VALUES (?, ?, ?, ?, ?, ?)");
        $insert->execute([$name, $username, $email, $hash, $role, $dept]);
    }
    echo "Done.\n";
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
