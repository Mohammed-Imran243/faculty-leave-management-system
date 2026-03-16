<?php
require_once __DIR__ . '/includes/config.php';
$stmt = $conn->query("SELECT COUNT(*) FROM users WHERE role='faculty'");
echo "Faculty count: " . $stmt->fetchColumn() . "\n";
$stmt = $conn->query("SELECT employee_code, designation FROM users WHERE designation IS NOT NULL LIMIT 5");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
