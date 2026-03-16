<?php
declare(strict_types=1);

require_once __DIR__ . '/SimpleJWT.php';

// Start Session securely
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        // 'cookie_secure' => true, // Uncomment if using HTTPS
        'cookie_samesite' => 'Strict',
        'use_strict_mode' => true
    ]);
}

// 1. Session Inactivity Timeout (30 minutes)
$timeout_duration = 1800; // 30 minutes in seconds

// Allow public access to auth endpoints, but protect everything else
$path = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : (isset($_SERVER['REQUEST_URI']) ? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : '/');
$is_auth_route = (strpos($path, 'login') !== false || strpos($path, 'register') !== false || strpos($_SERVER['SCRIPT_NAME'], 'auth.php') !== false);

if (!$is_auth_route && isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
    session_unset();
    session_destroy();
    http_response_code(401);
    echo json_encode(["error" => "Session expired due to inactivity."]);
    exit();
}
$_SESSION['last_activity'] = time();

// 2. CSRF Token Validation
// We only enforce CSRF on state-changing methods
$method = $_SERVER['REQUEST_METHOD'];
if (in_array($method, ['POST', 'PUT', 'DELETE'])) {
    // If it's the login route, we skip CSRF check, as they don't have a session yet
    $path = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : (isset($_SERVER['REQUEST_URI']) ? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : '/');
    if (strpos($path, 'login') === false && strpos($path, 'register') === false && strpos($_SERVER['SCRIPT_NAME'], 'auth.php') === false) {
        $headers = function_exists('apache_request_headers') ? apache_request_headers() : getallheaders();
        $csrf_token = $headers['X-CSRF-Token'] ?? ($headers['X-Csrf-Token'] ?? ($headers['x-csrf-token'] ?? ''));
        
        if (empty($csrf_token) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
            http_response_code(403);
            echo json_encode(["error" => "Invalid CSRF token"]);
            exit();
        }
    }
}

// 3. JWT Token Validation (Centralized)
$token = JWT::get_bearer_token();
$global_user = $token ? JWT::decode($token) : null;

// Allow public access to auth endpoints, but protect everything else
$path = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : (isset($_SERVER['REQUEST_URI']) ? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : '/');
if (strpos($path, '/login') === false && strpos($path, '/register') === false) {
    if (!$global_user) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(["error" => "Unauthorized"]);
        exit();
    }
}
