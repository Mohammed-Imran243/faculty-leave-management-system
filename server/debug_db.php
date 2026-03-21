<?php
require_once __DIR__ . '/core/bootstrap.php';
use App\Core\Database;

header('Content-Type: text/plain');

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    $stmt = $conn->query("SELECT id, username, LENGTH(username) as ulen, HEX(username) as uhex FROM users");
    $users = $stmt->fetchAll();

    foreach ($users as $user) {
        printf("ID: %d | Username: [%s] | Len: %d | Hex: %s\n", 
            $user['id'], 
            $user['username'], 
            $user['ulen'], 
            $user['uhex']
        );
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
