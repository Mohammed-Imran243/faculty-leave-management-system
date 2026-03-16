<?php
require_once __DIR__ . '/../includes/config.php';

$sql = file_get_contents(__DIR__ . '/sql/update_permissions_outpasses_enum.sql');

try {
    $conn->exec($sql);
    echo "Successfully updated ENUM statuses for permissions and outpasses.\\n";
} catch (PDOException $e) {
    echo "Error updating schema: " . $e->getMessage() . "\\n";
}
