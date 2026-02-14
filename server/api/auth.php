<?php
require_once '../config.php';
require_once '../SimpleJWT.php';
require_once '../audit.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '/';

// Router
if ($method === 'POST' && $path === '/register') {
    register($conn);
} elseif ($method === 'POST' && $path === '/login') {
    login($conn);
} else {
    http_response_code(404);
    echo json_encode(["error" => "Route not found"]);
}

function register($conn) {
    $data = json_decode(file_get_contents("php://input"));
    
    if(!isset($data->name) || !isset($data->username) || !isset($data->password)) {
        http_response_code(400);
        echo json_encode(["error" => "Missing fields", "received" => $data]);
        return;
    }

    $name = $data->name;
    $username = $data->username;
    $email = isset($data->email) ? $data->email : ''; // Email optional or secondary now? Let's keep it if provided, or empty.
    $password = $data->password;
    $department = isset($data->department) ? $data->department : '';
    $role = isset($data->role) ? $data->role : 'faculty'; // Default to faculty

    // HASHING PASSWORD
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    try {
        $stmt = $conn->prepare("INSERT INTO users (name, username, email, password_hash, role, department) VALUES (:name, :username, :email, :pass, :role, :dept)");
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':pass', $password_hash);
        $stmt->bindParam(':role', $role);
        $stmt->bindParam(':dept', $department);
        $stmt->execute();

        echo json_encode(["message" => "User registered successfully"]);
    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Registration failed: " . $e->getMessage()]);
    }
}

function login($conn) {
    $data = json_decode(file_get_contents("php://input"));

    if(!isset($data->username) || !isset($data->password)) {
        http_response_code(400);
        echo json_encode(["error" => "Missing fields"]);
        return;
    }

    // Accept login by username OR email
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = :login OR email = :login");
    $stmt->bindParam(':login', $data->username);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($data->password, $user['password_hash'])) {
        // GENERATE JWT
        $payload = [
            'id' => $user['id'],
            'role' => $user['role'],
            'name' => $user['name'],
            'department' => $user['department'],
            'iat' => time(),
            'exp' => time() + (60 * 60 * 24) // 24 hours
        ];
        $token = JWT::encode($payload);
        
        logAudit($conn, $user['id'], 'LOGIN_SUCCESS', 'User logged in');

        echo json_encode([
            "message" => "Login successful",
            "token" => $token,
            "user" => [
                "id" => $user['id'],
                "name" => $user['name'],
                "role" => $user['role'],
                "department" => $user['department']
            ]
        ]);
    } else {
        $uid = ($user && isset($user['id'])) ? $user['id'] : null;
        logAudit($conn, $uid, 'LOGIN_FAILED', ['username' => $data->username]);
        http_response_code(401);
        echo json_encode(["error" => "Invalid credentials"]);
    }
}
?>
