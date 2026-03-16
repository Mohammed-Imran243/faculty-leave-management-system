<?php
require_once __DIR__ . '/includes/config.php';
$stmt = $conn->query("DESCRIBE users");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
