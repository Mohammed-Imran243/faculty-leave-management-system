<?php
require_once __DIR__ . '/core/bootstrap.php';
use App\Core\Database;
use App\Services\SecurityService;

header('Content-Type: application/json');

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    // 1. Create leave_rules table (DDL - Implicitly commits in MySQL)
    $sql = "CREATE TABLE IF NOT EXISTS leave_rules (
        id INT AUTO_INCREMENT PRIMARY KEY,
        rule_name VARCHAR(100) NOT NULL,
        rule_value DECIMAL(10,2) NOT NULL,
        rule_period VARCHAR(50) NOT NULL DEFAULT 'monthly',
        description TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY(rule_name)
    )";
    $conn->exec($sql);

    // 2. Seed default rules (DML - Transactional)
    $conn->beginTransaction();
    
    $rules = [
        ['Casual Leave Limit', 12, 'monthly', 'Maximum Casual Leave days allowed per month'],
        ['Permission Limit', 1, 'monthly', 'Maximum Permissions allowed per month (combined with Outpass)'],
        ['Outpass Limit', 1, 'monthly', 'Maximum Outpasses allowed per month (combined with Permission)']
    ];

    $stmt = $conn->prepare("INSERT IGNORE INTO leave_rules (rule_name, rule_value, rule_period, description) VALUES (?, ?, ?, ?)");
    foreach ($rules as $rule) {
        $stmt->execute($rule);
    }

    // 3. Reset Admin and Principal passwords
    $adminHash = SecurityService::hashPassword('admin123');
    $stmt = $conn->prepare("UPDATE users SET password_hash = :hash WHERE username IN ('admin', 'principal')");
    $stmt->execute([':hash' => $adminHash]);
    
    // Reset Faculty passwords
    $facultyHash = SecurityService::hashPassword('faculty123');
    $stmt = $conn->prepare("UPDATE users SET password_hash = :hash WHERE username IN ('1101100', '281596')");
    $stmt->execute([':hash' => $facultyHash]);

    $conn->commit();
    echo json_encode(["status" => "success", "message" => "Database initialized and test passwords reset."]);
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>
