<?php
header('Content-Type: application/json');
require_once '../../includes/config.php';
require_once '../../includes/audit.php';

require_once '../../includes/auth_guard.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '/';

$user_data = $global_user;

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
    if (strtolower($user['role']) !== 'admin') {
        http_response_code(403);
        echo json_encode(["error" => "Admin access required"]);
        exit();
    }
}

function create_user($conn) {
    $data = json_decode(file_get_contents("php://input"));
    
    if(!isset($data->name) || !isset($data->password) || !isset($data->role) || !isset($data->employee_code)) {
        http_response_code(400);
        echo json_encode(["error" => "Missing fields"]);
        return;
    }

    $email = isset($data->email) ? $data->email : null;
    $emp_code = $data->employee_code;

    // Default username to 'user_' + random if not set (no longer relying on email prefix)
    $username = isset($data->username) ? $data->username : 'user_' . bin2hex(random_bytes(4));

    $hash = password_hash($data->password, PASSWORD_DEFAULT);
    $dept = isset($data->department) ? $data->department : '';

    try {
        $stmt = $conn->prepare("INSERT INTO users (name, username, employee_code, email, password_hash, role, department) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$data->name, $username, $emp_code, $email, $hash, $data->role, $dept]);
        $newId = $conn->lastInsertId();
        
        logAudit($conn, $user_data['id'], 'USER_CREATE', ['created_user_id' => $newId, 'username' => $username, 'role' => $data->role]);
        
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
        
        logAudit($conn, $user_data['id'], 'USER_DELETE', ['deleted_user_id' => $id]);
        
        echo json_encode(["message" => "User deleted"]);
    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => $e->getMessage()]);
    }
}

function list_users($conn) {
    try {
        $stmt = $conn->prepare("SELECT id, name, username, employee_code, role, department, created_at FROM users ORDER BY created_at DESC");
        $stmt->execute();
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => $e->getMessage()]);
    }
}

function list_faculty($conn) {
    try {
        // Return only what's needed for selection (Teaching roles + HoD for substitution)
        $stmt = $conn->prepare("SELECT id, name, department FROM users WHERE LOWER(role) IN ('faculty', 'hod', 'assistant professor (ap)', 'associate professor', 'professor') ORDER BY name ASC");
        $stmt->execute();
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => $e->getMessage()]);
    }
}
?>
