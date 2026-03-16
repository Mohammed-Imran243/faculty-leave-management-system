<?php
header('Content-Type: application/json');
require_once '../../includes/config.php';
require_once '../../includes/auth_guard.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '/';

$user = $global_user;

// Only Admins can modify rules. Faculty/HoD can view them.
if ($method === 'GET') {
    get_rules($conn);
} elseif ($method === 'PUT' && preg_match('/^\/(\d+)$/', $path, $matches)) {
    if (strtolower($user['role']) !== 'admin') {
        http_response_code(403);
        echo json_encode(["error" => "Admin access required"]);
        exit();
    }
    
    // CSRF Protection
    session_start();
    $headers = getallheaders();
    $csrf_header = isset($headers['X-CSRF-Token']) ? $headers['X-CSRF-Token'] : (isset($headers['X-Csrf-Token']) ? $headers['X-Csrf-Token'] : '');
    
    if (empty($csrf_header) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_header)) {
        http_response_code(403);
        echo json_encode(["error" => "Invalid CSRF token"]);
        exit();
    }
    
    update_rule($conn, $matches[1]);
} else {
    http_response_code(404);
}

function get_rules($conn) {
    try {
        $stmt = $conn->prepare("SELECT * FROM leave_rules ORDER BY id ASC");
        $stmt->execute();
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => $e->getMessage()]);
    }
}

function update_rule($conn, $id) {
    $data = json_decode(file_get_contents("php://input"));
    
    if (!isset($data->rule_value)) {
        http_response_code(400);
        echo json_encode(["error" => "Rule value is required"]);
        return;
    }

    try {
        $stmt = $conn->prepare("UPDATE leave_rules SET rule_value = ? WHERE id = ?");
        $stmt->execute([$data->rule_value, $id]);
        echo json_encode(["message" => "Rule updated successfully"]);
    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => $e->getMessage()]);
    }
}
?>
