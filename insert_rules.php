<?php
require_once 'includes/config.php';

$rules = [
    ['permission_start_time', 930, 'daily', 'Start time for morning permission (Format: HHMM)'],
    ['permission_end_time', 1030, 'daily', 'End time for morning permission (Format: HHMM)'],
    ['permission_evening_start', 1630, 'daily', 'Start time for evening permission (Format: HHMM)'],
    ['permission_evening_end', 1730, 'daily', 'End time for evening permission (Format: HHMM)'],
    ['permission_outpass_limit', 2, 'monthly', 'Combined limit for Permissions and Outpasses per month']
];

foreach ($rules as $rule) {
    try {
        $stmt = $conn->prepare("INSERT IGNORE INTO leave_rules (rule_name, rule_value, rule_period, description) VALUES (?, ?, ?, ?)");
        $stmt->execute($rule);
        echo "Inserted {$rule[0]}\n";
    } catch(Exception $e) {
        echo "Error on {$rule[0]}: " . $e->getMessage() . "\n";
    }
}
?>
