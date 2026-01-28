<?php
require_once 'config.php';

try {
    $conn->exec("DROP TABLE IF EXISTS audit_logs");
    echo "Dropped old audit_logs table.\n";
    
    $sql = "CREATE TABLE audit_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        action VARCHAR(50) NOT NULL,
        details TEXT,
        ip_address VARCHAR(45),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    )";
    $conn->exec($sql);
    echo "Created fresh audit_logs table.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
