<?php
namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Services\JwtService;

class AuthService {
    public static function login($username, $password) {
        $db = Database::getInstance();
        $stmt = $db->query("SELECT * FROM users WHERE username = :login1 OR email = :login2", [
            ':login1' => $username,
            ':login2' => $username
        ]);
        $user = $stmt->fetch();

        if ($user && SecurityService::verifyPassword($password, $user['password_hash'])) {
            $payload = [
                'id' => $user['id'],
                'role' => $user['role'],
                'name' => $user['name'],
                'department' => $user['department'],
                'iat' => time(),
                'exp' => time() + (60 * 60 * 24)
            ];
            
            $token = JwtService::encode($payload);

            return [
                "user" => [
                    "id" => $user['id'],
                    "name" => $user['name'],
                    "role" => $user['role'],
                    "department" => $user['department']
                ],
                "token" => $token,
                "csrf_token" => SecurityService::generateCsrfToken()
            ];
        }

        return false;
    }

    public static function validateToken($token) {
        return JwtService::decode($token);
    }
}
