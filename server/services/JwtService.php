<?php
namespace App\Services;

use App\Core\Config;

class JwtService {
    private static $secret;

    private static function getSecret() {
        if (!self::$secret) {
            self::$secret = Config::get('JWT_SECRET', 'default_secret_key_change_me');
        }
        return self::$secret;
    }

    public static function encode($payload) {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $base64UrlHeader = self::base64UrlEncode($header);
        $base64UrlPayload = self::base64UrlEncode(json_encode($payload));
        
        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, self::getSecret(), true);
        $base64UrlSignature = self::base64UrlEncode($signature);
        
        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }

    public static function decode($jwt) {
        $tokenParts = explode('.', $jwt);
        if (count($tokenParts) !== 3) return null;

        $header = json_decode(self::base64UrlDecode($tokenParts[0]), true);
        $payload = json_decode(self::base64UrlDecode($tokenParts[1]), true);
        $signatureProvided = $tokenParts[2];

        // Check expiration
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return null;
        }

        // Verify signature
        $base64UrlHeader = $tokenParts[0];
        $base64UrlPayload = $tokenParts[1];
        $signatureCheck = self::base64UrlEncode(hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, self::getSecret(), true));

        if ($signatureCheck === $signatureProvided) {
            return $payload;
        }

        return null;
    }

    private static function base64UrlEncode($data) {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
    }

    private static function base64UrlDecode($data) {
        $url = str_replace(['-', '_'], ['+', '/'], $data);
        $padding = strlen($url) % 4;
        if ($padding) {
            $url .= str_repeat('=', 4 - $padding);
        }
        return base64_decode($url);
    }

    public static function getBearerToken() {
        $headers = getallheaders();
        if (isset($headers['Authorization'])) {
            if (preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches)) {
                return $matches[1];
            }
        }
        return null;
    }
}
