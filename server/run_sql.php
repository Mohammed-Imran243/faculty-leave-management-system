<?php
require_once 'config.php';

if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.");
}

$file = $argv[1] ?? 'db_update_v3.sql';
$path = __DIR__ . '/' . $file;

if (!file_exists($path)) {
    die("Error: SQL file '$file' not found.\n");
}

echo "Running SQL update from: $file\n";

try {
    $sql = file_get_contents($path);
    $conn->exec($sql);
    echo "Success: Database updated successfully.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
