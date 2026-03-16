<?php
namespace App\Core;

class Config {
    private static $env = [];

    public static function load($path) {
        if (!file_exists($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            self::$env[$name] = $value;
            $_ENV[$name] = $value;
            putenv("$name=$value");
        }
    }

    public static function get($key, $default = null) {
        return self::$env[$key] ?? $default;
    }
}
