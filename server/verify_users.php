<?php
require_once '../includes/config.php';

// Simulate a user creation request without email
$data = [
    'name' => 'Test Professor',
    'username' => 'testprof' . rand(100, 999),
    'password' => 'password123',
    'role' => 'Professor',
    'department' => 'CSE'
];

// We bypass the auth guard for testing if needed, but here we can just test the inner function logic 
// or mock the request if it's easier. 
// Since users.php is a set of functions, let's just test the SQL execution.

$username = $data['username'];
$hash = password_hash($data['password'], PASSWORD_DEFAULT);
$dept = $data['department'];
$role = $data['role'];
$email = null;

try {
    $stmt = $conn->prepare("INSERT INTO users (name, username, email, password_hash, role, department) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$data['name'], $username, $email, $hash, $role, $dept]);
    $id = $conn->lastInsertId();
    echo "Successfully created user with ID: $id\n";
    
    // Check if it's there
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    print_r($user);
    
    // Clean up
    $conn->exec("DELETE FROM users WHERE id = $id");
    echo "Test user deleted.\n";
} catch (PDOException $e) {
    echo "Verification failed: " . $e->getMessage() . "\n";
}
?>
