<?php
require_once '../config.php';
require_once '../config.php';
require_once '../libs/SimpleJWT.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '/';

// Auth Check
$token = JWT::get_bearer_token();
$user = $token ? JWT::decode($token) : null;

if (!$user || ($user['role'] !== 'principal' && $user['role'] !== 'admin')) {
    http_response_code(403);
    echo json_encode(["error" => "Unauthorized"]);
    exit();
}

if ($method === 'GET') {
    $response = [];

    // 1. Leaves Today (Active)
    $today = date('Y-m-d');
    $sqlToday = "SELECT COUNT(*) FROM leave_requests WHERE ? BETWEEN start_date AND end_date AND hod_status = 'Approved' AND principal_status = 'Approved'";
    $stmt = $conn->prepare($sqlToday);
    $stmt->execute([$today]);
    $response['leaves_today'] = $stmt->fetchColumn();

    // 2. Pending Approvals (Principal)
    $sqlPending = "SELECT COUNT(*) FROM leave_requests WHERE hod_status = 'Approved' AND principal_status = 'Pending'";
    $stmt = $conn->query($sqlPending);
    $response['pending_approvals'] = $stmt->fetchColumn();

    // 3. Dept Breakdown (Active Leaves Today) - OR maybe Total Leaves this Month?
    // Let's do "Leaves Taken This Month per Dept" to be more useful.
    $firstDay = date('Y-m-01');
    $lastDay = date('Y-m-t');
    
    $sqlDept = "SELECT u.department, COUNT(*) as count 
                FROM leave_requests l
                JOIN users u ON l.user_id = u.id
                WHERE l.start_date BETWEEN ? AND ? 
                AND l.principal_status = 'Approved'
                GROUP BY u.department";
    $stmt = $conn->prepare($sqlDept);
    $stmt->execute([$firstDay, $lastDay]);
    $response['dept_stats'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($response);
} else {
    http_response_code(404);
}
?>
