<?php
require_once 'config.php';
$leaves = $conn->query("SELECT id, user_id, principal_status FROM leave_requests")->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($leaves, JSON_PRETTY_PRINT);
?>
