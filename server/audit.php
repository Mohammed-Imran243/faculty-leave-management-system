<?php
// Audit Logging Helper

function logAudit($conn, $userId, $action, $details = '') {
    if (!$conn) return;
    
    // Get Client IP
    $ip = 'CLI';
    if (isset($_SERVER['REMOTE_ADDR'])) {
        $ip = $_SERVER['REMOTE_ADDR'];
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
    }

    try {
        $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
        // Convert array details to JSON
        if (is_array($details) || is_object($details)) {
            $details = json_encode($details);
        }
        $stmt->execute([$userId, $action, $details, $ip]);
    } catch (Exception $e) {
        // Silently fail logging to avoid breaking main flow
        error_log("Audit Log Failed: " . $e->getMessage());
    }
}
}
