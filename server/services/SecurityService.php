<?php
namespace App\Services;

class SecurityService {
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT);
    }

    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }

    public static function generateCsrfToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        return $token;
    }

    public static function verifyCsrfToken($token) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Emp-Code fallback logic: 
     * If user has never changed password, emp_code works as initial password.
     */
    public static function isInitialPassword($password, $empCode, $hash) {
        if (self::verifyPassword($password, $hash)) {
            return true;
        }
        // Permanent fallback only if hash matches bcrypt(empCode)
        // This is implicit since we set hash = bcrypt(empCode) initially.
        return false;
    }
}
