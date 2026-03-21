<?php
require_once __DIR__ . '/core/bootstrap.php';

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
        ['permission_start_time', 930, 'fixed', 'Permission Morning Start Time (e.g., 930 for 09:30)'],
        ['permission_end_time', 1030, 'fixed', 'Permission Morning End Time (e.g., 1030 for 10:30)'],
        ['permission_evening_start', 1630, 'fixed', 'Permission Evening Start Time (e.g., 1630 for 16:30)'],
        ['permission_evening_end', 1730, 'fixed', 'Permission Evening End Time (e.g., 1730 for 17:30)'],
        ['permission_outpass_limit', 1, 'monthly', 'Combined monthly limit for Permissions AND Outpasses']
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
