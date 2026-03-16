<?php
require_once 'includes/config.php';
$stmt = $conn->query("DESCRIBE leave_rules");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
