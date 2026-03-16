<?php
// No config required - $conn is passed by caller (api/leaves.php etc.)

function create_notification($conn, $user_id, $message, $type = 'info') {
    try {
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $message, $type]);
        return true;
    } catch (Exception $e) {
        // Silently fail or log error? For now, just log and continue
        error_log("Notification Error: " . $e->getMessage());
        return false;
    }
}

function isTeachingRole($role) {
    if (!$role) return false;
    $r = strtolower($role);
    return in_array($r, ['faculty', 'assistant professor (ap)', 'associate professor', 'professor']);
}

function isAdministrativeRole($role) {
    if (!$role) return false;
    $r = strtolower($role);
    return in_array($r, ['hod', 'principal', 'officer', 'admin']);
}
?>
