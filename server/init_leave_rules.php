<?php
require_once __DIR__ . '/../includes/config.php';

try {
    $conn->beginTransaction();

    // Create leave_rules table if it doesn't exist
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

    // Seed default rules
    $rules = [
        ['Casual Leave Limit', 12, 'monthly', 'Maximum Casual Leave days allowed per month'],
        ['Permission Limit', 1, 'monthly', 'Maximum Permissions allowed per month (combined with Outpass)'],
        ['Outpass Limit', 1, 'monthly', 'Maximum Outpasses allowed per month (combined with Permission)']
    ];

    $stmt = $conn->prepare("INSERT IGNORE INTO leave_rules (rule_name, rule_value, rule_period, description) VALUES (?, ?, ?, ?)");
    foreach ($rules as $rule) {
        $stmt->execute($rule);
    }

    $conn->commit();
    echo "leave_rules table created and seeded successfully.";
} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo "Error: " . $e->getMessage();
}
?>
