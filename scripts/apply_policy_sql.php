<?php
require_once '../../includes/config.php';

try {
    $sql = file_get_contents('../sql/db_update_policy.sql');
    $conn->exec($sql);
    echo "Successfully applied database migration.\n";
} catch (PDOException $e) {
    echo "Error applying migration: " . $e->getMessage() . "\n";
}
