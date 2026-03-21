<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../core/auth_guard.php';

use App\Repositories\UserRepository;
use App\Services\SecurityService;

$method = $_SERVER['REQUEST_METHOD'];
$path = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '/';
$userRepo = new UserRepository();

// Check Admin role for write operations
function ensureAdmin($global_user) {
    if (strtolower($global_user['role']) !== 'admin') {
        http_response_code(403);
        die(json_encode(["error" => "Admin access required"]));
    }
}

try {
    if ($method === 'GET') {
        if ($path === '/me') {
            echo json_encode($userRepo->findById($global_user['id']));
            exit;
        }

        if ($path === '/faculty') {
            // Special case for faculty selection in leave forms
            echo json_encode($userRepo->search('', '', 1, 1000)['users']);
            exit;
        }
        
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
        $search = $_GET['search'] ?? '';
        $dept = $_GET['dept'] ?? '';

        echo json_encode($userRepo->search($search, $dept, $page, $limit));

    } elseif ($method === 'POST') {
        ensureAdmin($global_user);
        $data = json_decode(file_get_contents("php://input"), true);
        
        if ($path === '/create') {
            $data['password_hash'] = SecurityService::hashPassword($data['password']);
            $id = $userRepo->create($data);
            echo json_encode(["message" => "User created", "id" => $id]);
        }
        
    } elseif ($method === 'DELETE') {
        ensureAdmin($global_user);
        if (preg_match('/^\/(\d+)$/', $path, $matches)) {
            $userRepo->delete($matches[1]);
            echo json_encode(["message" => "User deleted"]);
        }
        
    } else {
        http_response_code(405);
        echo json_encode(["error" => "Method not allowed"]);
    }
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
