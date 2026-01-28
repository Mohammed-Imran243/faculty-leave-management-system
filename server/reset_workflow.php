<?php
require_once 'config.php';

try {
    // 1. Clear Data Tables
    $conn->exec("SET FOREIGN_KEY_CHECKS = 0");
    $conn->exec("TRUNCATE TABLE leave_requests");
    $conn->exec("TRUNCATE TABLE leave_substitutions");
    $conn->exec("TRUNCATE TABLE notifications");
    
    // 2. Reset Users (Delete and Recreate Test Users)
    $conn->exec("DELETE FROM users WHERE username IN ('requester', 'substitute', 'hod', 'principal')");
    $conn->exec("SET FOREIGN_KEY_CHECKS = 1");

    $password_hash = password_hash('123', PASSWORD_DEFAULT);

    // Requester (Faculty)
    $stmt = $conn->prepare("INSERT INTO users (name, username, email, password_hash, role, department) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute(['Requester Faculty', 'requester', 'req@test.com', $password_hash, 'faculty', 'CSE']);

    // Substitute (Faculty)
    $stmt->execute(['Substitute Faculty', 'substitute', 'sub@test.com', $password_hash, 'faculty', 'CSE']);

    // HoD
    $stmt->execute(['Head of Dept', 'hod', 'hod@test.com', $password_hash, 'hod', 'CSE']);

    // Principal
    $stmt->execute(['Principal', 'principal', 'principal@test.com', $password_hash, 'principal', 'Administration']);

    echo "Data Reset Successfully.\n";
    echo "Users Created:\n";
    echo "- requester / 123\n";
    echo "- substitute / 123\n";
    echo "- hod / 123\n";
    echo "- principal / 123\n";

} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
