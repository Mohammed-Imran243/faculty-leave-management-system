<?php
require_once 'config.php';

try {
    $conn->beginTransaction();

    // 1. Clear dependent tables first to avoid Foregin Key errors
    $conn->exec("DELETE FROM notifications");
    $conn->exec("DELETE FROM approvals");
    $conn->exec("DELETE FROM leave_substitutions");
    $conn->exec("DELETE FROM leave_requests");

    // 2. Delete all users EXCEPT admin
    // Assuming 'admin' is the username for the admin.
    $stmt = $conn->prepare("DELETE FROM users WHERE username != 'admin'");
    $stmt->execute();
    
    // 3. Ensure admin exists and has known password
    $adminPass = password_hash('admin123', PASSWORD_DEFAULT);
    // Insert if not exists, or update if exists
    // Check if admin exists
    $stmt = $conn->query("SELECT id FROM users WHERE username = 'admin'");
    if ($stmt->fetch()) {
        // Update
        $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE username = 'admin'");
        $stmt->execute([$adminPass]);
        echo "Admin password reset to 'admin123'.\n";
    } else {
        // Create
        $stmt = $conn->prepare("INSERT INTO users (name, username, email, password_hash, role, department) VALUES ('Admin', 'admin', 'admin@college.edu', ?, 'admin', 'Administration')");
        $stmt->execute([$adminPass]);
        echo "Admin user created.\n";
    }

    $conn->commit();
    echo "All other users and data cleared successfully.\n";

} catch (PDOException $e) {
    $conn->rollBack();
    echo "Error: " . $e->getMessage();
}
?>
