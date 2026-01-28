<?php
require_once 'config.php';

try {
    $conn->beginTransaction();

    $pass = '123456';
    $hash = password_hash($pass, PASSWORD_DEFAULT);

    // List of users to restore
    $users = [
        ['name' => 'Hari', 'username' => 'hari', 'role' => 'faculty', 'dept' => 'CSE'],
        ['name' => 'Sudha', 'username' => 'sudha', 'role' => 'faculty', 'dept' => 'CSE'],
        ['name' => 'Head of Dept', 'username' => 'hod', 'role' => 'hod', 'dept' => 'CSE'],
        ['name' => 'Principal', 'username' => 'principal', 'role' => 'principal', 'dept' => 'Administration']
    ];

    $stmt = $conn->prepare("INSERT INTO users (name, username, email, password_hash, role, department) VALUES (?, ?, ?, ?, ?, ?)");

    foreach ($users as $u) {
        // Check if exists to avoid duplication error (though we cleared them, best to be safe)
        $check = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $check->execute([$u['username']]);
        if (!$check->fetch()) {
            $email = $u['username'] . '@college.edu';
            $stmt->execute([$u['name'], $u['username'], $email, $hash, $u['role'], $u['dept']]);
            echo "Restored User: " . $u['username'] . "\n";
        } else {
            echo "User already exists: " . $u['username'] . "\n";
        }
    }

    $conn->commit();
    echo "Restoration Complete. Password for all is '$pass'.\n";

} catch (PDOException $e) {
    $conn->rollBack();
    echo "Error: " . $e->getMessage();
}
?>
