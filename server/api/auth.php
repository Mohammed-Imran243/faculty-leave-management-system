<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../core/bootstrap.php';
require_once __DIR__ . '/../core/auth_guard.php';
require_once __DIR__ . '/../core/audit.php';

use App\Services\AuthService;
use App\Repositories\UserRepository;
use App\Services\SecurityService;

$method = $_SERVER['REQUEST_METHOD'];
$path = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '/';

if ($method === 'POST' && $path === '/login') {
    try {
        $data = json_decode(file_get_contents("php://input"), true);
        if (empty($data['username']) || empty($data['password'])) {
            http_response_code(400);
            die(json_encode(["error" => "Missing credentials"]));
        }

        $result = AuthService::login($data['username'], $data['password']);
        if ($result) {
            echo json_encode($result);
        } else {
            http_response_code(401);
            echo json_encode(["error" => "Invalid username or password"]);
        }
    } catch (\Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => $e->getMessage()]);
    }
} elseif ($method === 'POST' && $path === '/register') {
    try {
        $data = json_decode(file_get_contents("php://input"), true);
        $userRepo = new UserRepository();
        
        $data['password_hash'] = SecurityService::hashPassword($data['password']);
        $id = $userRepo->create($data);
        
        echo json_encode(["message" => "User registered", "id" => $id]);
    } catch (\Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => $e->getMessage()]);
    }
} else {
    http_response_code(404);
    echo json_encode(["error" => "Not found"]);
}
