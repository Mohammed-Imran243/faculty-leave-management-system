<?php
require_once '../config.php';

require_once '../libs/SimpleJWT.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '/';

// Middleware: Verify Token
$token = JWT::get_bearer_token();
$user_data = null;
if ($token) $user_data = JWT::decode($token);

if (!$user_data) {
    http_response_code(403);
    echo json_encode(["error" => "Unauthorized"]);
    exit();
}

// Router
if ($method === 'POST' && $path === '/create') {
    require_admin($user_data);
    create_user($conn);
} elseif ($method === 'DELETE' && preg_match('/^\/(\d+)$/', $path, $matches)) {
    require_admin($user_data);
    delete_user($conn, $matches[1]);
} elseif ($method === 'GET' && $path === '/') {
    require_admin($user_data);
    list_users($conn);
} elseif ($method === 'GET' && $path === '/faculty') {
    // Accessible to all logged-in users (needed for substitution selection)
    list_faculty($conn);
} else {
    http_response_code(404);
}

function require_admin($user) {
    if ($user['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(["error" => "Admin access required"]);
        exit();
    }
}

function create_user($conn) {
    $data = json_decode(file_get_contents("php://input"));
    
    if(!isset($data->name) || !isset($data->email) || !isset($data->password) || !isset($data->role)) {
        http_response_code(400);
        echo json_encode(["error" => "Missing fields"]);
        return;
    }

    // Default username to email prefix if not set
    $username = isset($data->username) ? $data->username : explode('@', $data->email)[0];

    $hash = password_hash($data->password, PASSWORD_DEFAULT);
    $dept = isset($data->department) ? $data->department : '';

    try {
        $stmt = $conn->prepare("INSERT INTO users (name, username, email, password_hash, role, department) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$data->name, $username, $data->email, $hash, $data->role, $dept]);
        echo json_encode(["message" => "User created successfully"]);
    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => $e->getMessage()]);
    }
}

function delete_user($conn, $id) {
    try {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(["message" => "User deleted"]);
    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => $e->getMessage()]);
    }
}

function list_users($conn) {
    try {
        $stmt = $conn->query("SELECT id, name, email, role, department, created_at FROM users ORDER BY created_at DESC");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => $e->getMessage()]);
    }
}

function list_faculty($conn) {
    try {
        // Return only what's needed for selection
        $stmt = $conn->query("SELECT id, name, department FROM users WHERE role IN ('faculty', 'hod') ORDER BY name ASC");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => $e->getMessage()]);
    }
}
?>
