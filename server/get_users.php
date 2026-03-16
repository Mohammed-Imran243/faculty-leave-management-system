<?php
require_once __DIR__ . '/../includes/config.php';
$fac = $conn->query("SELECT username FROM users WHERE role = 'faculty' LIMIT 1")->fetchColumn();
$hod = $conn->query("SELECT username FROM users WHERE role = 'hod' LIMIT 1")->fetchColumn();
$princ = $conn->query("SELECT username FROM users WHERE role = 'principal' LIMIT 1")->fetchColumn();

echo "Fac: $fac, HOD: $hod, Princ: $princ\n";
