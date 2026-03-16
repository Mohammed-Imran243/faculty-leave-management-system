<?php
namespace App\Core;

class ExceptionHandler {
    public static function register() {
        set_exception_handler([self::class, 'handleException']);
        set_error_handler([self::class, 'handleError']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    public static function handleException($e) {
        $isApi = self::isApiRequest();
        
        $code = $e->getCode() ?: 500;
        if (!is_numeric($code) || $code < 100 || $code > 599) $code = 500;

        self::logError($e);

        if ($isApi) {
            http_response_code($code);
            echo json_encode([
                "error" => "An internal server error occurred.",
                "message" => (Config::get('APP_ENV') === 'development') ? $e->getMessage() : "Please contact support if the issue persists.",
                "trace" => (Config::get('APP_ENV') === 'development') ? $e->getTrace() : null
            ]);
        } else {
            // Friendly HTML page
            echo "<h1>Something went wrong</h1>";
            if (Config::get('APP_ENV') === 'development') {
                echo "<p>" . $e->getMessage() . "</p>";
                echo "<pre>" . $e->getTraceAsString() . "</pre>";
            } else {
                echo "<p>Please try again later.</p>";
            }
        }
        exit;
    }

    public static function handleError($errno, $errstr, $errfile, $errline) {
        throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
    }

    public static function handleShutdown() {
        $error = error_get_last();
        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            self::handleException(new \ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']));
        }
    }

    private static function isApiRequest() {
        return (strpos($_SERVER['REQUEST_URI'] ?? '', '/server/api/') !== false) || 
               (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
    }

    private static function logError($e) {
        $logPath = __DIR__ . '/../../server/logs/error.log';
        if (!is_dir(dirname($logPath))) {
            mkdir(dirname($logPath), 0777, true);
        }
        $msg = "[" . date('Y-m-d H:i:s') . "] " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n" . $e->getTraceAsString() . "\n\n";
        error_log($msg, 3, $logPath);
    }
}
