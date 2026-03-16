<?php
require_once '../../includes/config.php';
// Get a faculty user and an admin user
$stmt = $conn->prepare("SELECT id, role FROM users WHERE role = 'faculty' LIMIT 1");
$stmt->execute();
$fac = $stmt->fetch();
if (!$fac) die("No faculty user found.\n");

$stmtAdmin = $conn->prepare("SELECT id, role FROM users WHERE role = 'admin' LIMIT 1");
$stmtAdmin->execute();
$adm = $stmtAdmin->fetch();
if (!$adm) {
    // try to find someone else or create one
    $conn->exec("INSERT INTO users (name, email, password_hash, role) VALUES ('Admin Pol', 'admin_pol@test.com', '123', 'admin')");
    $adm = ['id' => $conn->lastInsertId(), 'role' => 'admin'];
}

$facUser = ['id' => $fac['id'], 'role' => 'faculty'];
$adminUser = ['id' => $adm['id'], 'role' => 'admin'];

// Mock apply_leave function instead of including leaves.php since leaves.php reads php://input and runs exit()
// Wait, leaves.php doesn't run exit() unless there's an error, and even then, we can't mock php://input easily without stream wrappers.
// Actually, let's just use php stream wrapper or cURL but with correct base url.
// Let's print the base url.
echo "Users mapped: Faculty " . $facUser['id'] . ", Admin " . $adminUser['id'] . "\n";

?>
