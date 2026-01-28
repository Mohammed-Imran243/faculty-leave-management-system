<?php
require_once 'config.php';

$sqlFile = file_get_contents('db_update_v4.sql');
$queries = explode(';', $sqlFile);

foreach ($queries as $sql) {
    if (trim($sql) != '') {
        try {
            $conn->exec($sql);
            echo "Executed: " . substr($sql, 0, 50) . "...\n";
        } catch(PDOException $e) {
            echo "Error executing: " . substr($sql, 0, 50) . "...\n";
            echo "Message: " . $e->getMessage() . "\n";
        }
    }
}
?>
