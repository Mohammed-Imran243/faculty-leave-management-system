<?php
require_once 'config.php';

$sql = "CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(50) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
)";

try {
    $conn->exec($sql);
    echo "Table audit_logs created successfully.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
