<?php
header('Content-Type: application/json');
require_once '../../includes/config.php';
require_once '../../includes/auth_guard.php';

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'GET') {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit();
}

$user = $global_user;
$pending_count = 0;

try {
    if ($user['role'] === 'faculty') {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM leave_substitutions WHERE substitute_user_id = ? AND status = 'PENDING'");
        $stmt->execute([$user['id']]);
        $pending_count = (int)$stmt->fetchColumn();
    } elseif ($user['role'] === 'hod') {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM leave_requests l JOIN users u ON l.user_id = u.id WHERE l.hod_status = 'Pending' AND u.department = ?");
        $stmt->execute([$user['department']]);
        $pending_count = (int)$stmt->fetchColumn();
    } elseif ($user['role'] === 'principal') {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM leave_requests l JOIN users u ON l.user_id = u.id WHERE l.hod_status = 'Approved' AND l.principal_status = 'Pending'");
        $stmt->execute();
        $pending_count = (int)$stmt->fetchColumn();
    }
    
    // For admin, maybe pending users? Let's leave at 0 for now.
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
    exit();
}

echo json_encode(["pending_count" => $pending_count]);
