<?php
require_once 'config.php';

function create_notification($conn, $user_id, $message) {
    try {
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
        $stmt->execute([$user_id, $message]);
        return true;
    } catch (Exception $e) {
        // Silently fail or log error? For now, just log and continue
        error_log("Notification Error: " . $e->getMessage());
        return false;
    }
}
?>
