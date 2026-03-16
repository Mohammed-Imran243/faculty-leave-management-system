<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../core/bootstrap.php';
require_once __DIR__ . '/../core/auth_guard.php';
require_once __DIR__ . '/../core/audit.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit();
}

$user_data = $global_user;

// Verify Admin Access
if (strtolower($user_data['role']) !== 'admin') {
    http_response_code(403);
    echo json_encode(["error" => "Admin access required"]);
    exit();
}

$data = json_decode(file_get_contents("php://input"));

if (!$data || !isset($data->action)) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid request"]);
    exit();
}

if ($data->action === 'update') {
    if (!isset($data->userId) || !isset($data->username) || !isset($data->role)) {
        http_response_code(400);
        echo json_encode(["error" => "Missing required fields"]);
        exit();
    }

    $id = $data->userId;
    $username = $data->username;
    $role = $data->role;
    $name = isset($data->name) ? $data->name : '';
    $dept = isset($data->department) ? $data->department : '';
    $password = isset($data->password) ? $data->password : null;

    try {
        // Build query
        $sql = "UPDATE users SET username = ?, role = ?, name = ?, department = ?";
        $params = [$username, $role, $name, $dept];

        if ($password && !empty($password)) {
            $sql .= ", password_hash = ?";
            $params[] = password_hash($password, PASSWORD_DEFAULT);
        }

        $sql .= " WHERE id = ?";
        $params[] = $id;

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);

        logAudit($conn, $user_data['id'], 'USER_UPDATE', ['updated_user_id' => $id, 'data' => ['username' => $username, 'role' => $role, 'department' => $dept]]);

        echo json_encode(["message" => "User updated successfully"]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Database error: " . $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(["error" => "Unsupported action"]);
}
?>
