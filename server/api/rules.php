<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../core/auth_guard.php';

use App\Repositories\RuleRepository;
use App\Services\SecurityService;

$method = $_SERVER['REQUEST_METHOD'];
$path = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '/';
$ruleRepo = new RuleRepository();

try {
    if ($method === 'GET') {
        echo json_encode($ruleRepo->getAll());
    } elseif ($method === 'PUT' && preg_match('/^\/(\d+)$/', $path, $matches)) {
        if (strtolower($global_user['role']) !== 'admin') {
            http_response_code(403);
            die(json_encode(["error" => "Admin access required"]));
        }

        $data = json_decode(file_get_contents("php://input"), true);
        if (!isset($data['rule_value'])) {
            http_response_code(400);
            die(json_encode(["error" => "Rule value is required"]));
        }

        $ruleRepo->update($matches[1], $data['rule_value']);
        echo json_encode(["message" => "Rule updated successfully"]);
    } else {
        http_response_code(404);
        echo json_encode(["error" => "Not found"]);
    }
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
