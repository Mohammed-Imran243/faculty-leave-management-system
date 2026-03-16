<?php
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/../src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $parts = explode('\\', $relative_class);
    
    if (count($parts) >= 2) {
        $dir = strtolower($parts[0]);
        $subPath = implode('/', array_slice($parts, 1));
        $file = __DIR__ . "/../$dir/$subPath.php";
    } else {
        $file = __DIR__ . "/../" . str_replace('\\', '/', $relative_class) . ".php";
    }

    if (file_exists($file)) {
        require $file;
    }
});
