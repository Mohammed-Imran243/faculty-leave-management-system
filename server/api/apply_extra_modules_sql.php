<?php
require_once '../../includes/config.php';

try {
    $sql = file_get_contents('../sql/db_extra_modules.sql');
    $conn->exec($sql);
    echo "Successfully created tables for Permission, Outpass, and Notifications modules.\n";
} catch (PDOException $e) {
    echo "Error applying schema: " . $e->getMessage() . "\n";
}
