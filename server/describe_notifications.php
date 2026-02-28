<?php
require 'config.php';
try {
    $stmt = $conn->query("DESCRIBE notifications");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo $col['Field'] . " (" . $col['Type'] . ") Default: " . $col['Default'] . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
