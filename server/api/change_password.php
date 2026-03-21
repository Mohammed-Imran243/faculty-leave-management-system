<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../core/auth_guard.php';

use App\Repositories\UserRepository;
use App\Services\SecurityService;

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit;
}

try {
    $data = json_decode(file_get_contents("php://input"), true);
    
    if (empty($data['current_password']) || empty($data['new_password'])) {
        http_response_code(400);
        echo json_encode(["error" => "Current and new passwords are required"]);
        exit;
    }

    $userId = $global_user['id'];
    $userRepo = new UserRepository();
    $user = $userRepo->findById($userId);

    if (!$user || !SecurityService::verifyPassword($data['current_password'], $user['password_hash'])) {
        http_response_code(401);
        echo json_encode(["error" => "Invalid current password"]);
        exit;
    }

    $newHash = SecurityService::hashPassword($data['new_password']);
    $userRepo->update($userId, ['password_hash' => $newHash]);

    echo json_encode(["message" => "Password updated successfully"]);

} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
?>
