<?php
require_once '../config.php';

require_once '../libs/SimpleJWT.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '/';

$token = JWT::get_bearer_token();
$user = $token ? JWT::decode($token) : null;

if (!$user) {
    http_response_code(403);
    echo json_encode(["error" => "Unauthorized"]);
    exit();
}

if ($method === 'GET' && $path === '/') {
    get_my_notifications($conn, $user);
} elseif ($method === 'PUT' && preg_match('/^\/(\d+)\/read$/', $path, $matches)) {
    mark_as_read($conn, $user, $matches[1]);
} elseif ($method === 'PUT' && $path === '/read-all') {
    mark_all_read($conn, $user);
} else {
    http_response_code(404);
}

function get_my_notifications($conn, $user) {
    try {
        // Fetch unread notifications, or last 20? 
        // Let's fetch all unread, plus last 5 read ones maybe? 
        // For simplicity: All unread + last 10
        $stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY is_read ASC, created_at DESC LIMIT 50");
        $stmt->execute([$user['id']]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Database Error: " . $e->getMessage()]);
    }
}

function mark_as_read($conn, $user, $id) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user['id']]);
    echo json_encode(["message" => "Marked as read"]);
}

function mark_all_read($conn, $user) {
    try {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user['id']]);
        echo json_encode(["message" => "All notifications marked as read"]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Database Error: " . $e->getMessage()]);
    }
}
?>
