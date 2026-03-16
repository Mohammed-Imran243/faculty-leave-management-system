<?php
require_once '../includes/config.php';

// Verification script for Edit User feature
$testUserId = null;

try {
    // 1. Create a test user
    echo "Testing User Creation...\n";
    $createData = [
        'name' => 'Edit Test User',
        'username' => 'edittest' . rand(100, 999),
        'employee_code' => 'EMP' . rand(1000, 9999),
        'password' => 'pass123',
        'role' => 'Professor',
        'department' => 'IT'
    ];
    
    $stmt = $conn->prepare("INSERT INTO users (name, username, employee_code, password_hash, role, department) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$createData['name'], $createData['username'], $createData['employee_code'], password_hash($createData['password'], PASSWORD_DEFAULT), $createData['role'], $createData['department']]);
    $testUserId = $conn->lastInsertId();
    echo "Created test user ID: $testUserId\n";

    // 2. Test Update via manage_user.php logic (simulated)
    echo "Testing User Update...\n";
    $updateData = [
        'action' => 'update',
        'userId' => $testUserId,
        'username' => $createData['username'] . '_upd',
        'role' => 'HOD',
        'name' => 'Updated Name',
        'department' => 'Physics'
    ];

    $sql = "UPDATE users SET username = ?, role = ?, name = ?, department = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$updateData['username'], $updateData['role'], $updateData['name'], $updateData['department'], $updateData['userId']]);
    
    echo "Update executed.\n";

    // 3. Verify
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$testUserId]);
    $updatedUser = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($updatedUser['username'] === $updateData['username'] && $updatedUser['role'] === $updateData['role']) {
        echo "VERIFICATION SUCCESS: User updated correctly.\n";
    } else {
        echo "VERIFICATION FAILED: User not updated correctly.\n";
        print_r($updatedUser);
    }

    // Clean up
    if ($testUserId) {
        $conn->exec("DELETE FROM users WHERE id = $testUserId");
        echo "Test user deleted.\n";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    if ($testUserId) {
        $conn->exec("DELETE FROM users WHERE id = $testUserId");
    }
}
?>
