<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use App\Services\AuthService;
use App\Services\SecurityService;

// 1. Session Inactivity Timeout (30 minutes)
$timeout_duration = 1800; // 30 minutes
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
    session_unset();
    session_destroy();
    http_response_code(401);
    die(json_encode(["error" => "Session expired"]));
}
$_SESSION['last_activity'] = time();

// 2. CSRF Token Validation
$method = $_SERVER['REQUEST_METHOD'];
$path = $_SERVER['REQUEST_URI'];
$is_auth_route = (strpos($path, 'login') !== false || strpos($path, 'register') !== false || strpos($_SERVER['SCRIPT_NAME'], 'auth.php') !== false);

if (!$is_auth_route && in_array($method, ['POST', 'PUT', 'DELETE'])) {
    $headers = getallheaders();
    $token = $headers['X-CSRF-Token'] ?? '';
    if (!SecurityService::verifyCsrfToken($token)) {
        http_response_code(403);
        die(json_encode(["error" => "Invalid CSRF token"]));
    }
}

// 3. JWT Token Validation
use App\Services\JwtService;
$token = JwtService::getBearerToken();
$global_user = $token ? AuthService::validateToken($token) : null;

if (!$is_auth_route && !$global_user) {
    http_response_code(401);
    die(json_encode(["error" => "Unauthorized"]));
}

