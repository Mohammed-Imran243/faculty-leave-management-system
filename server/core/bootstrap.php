<?php
require_once __DIR__ . '/Autoloader.php';

use App\Core\Config;
use App\Core\ExceptionHandler;

// Load Environment
Config::load(__DIR__ . '/../../.env');

// Register Global Exception Handler
ExceptionHandler::register();

// Provide global connection for legacy scripts
$conn = \App\Core\Database::getInstance()->getConnection();

// Start Session with secure parameters
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure' => (Config::get('APP_ENV') === 'production'),
        'cookie_samesite' => 'Strict',
        'use_strict_mode' => true
    ]);
}
