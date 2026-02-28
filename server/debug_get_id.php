<?php
require 'config.php';
$stmt=$conn->query('SELECT id FROM leave_requests ORDER BY id DESC LIMIT 1');
echo $stmt->fetchColumn();
