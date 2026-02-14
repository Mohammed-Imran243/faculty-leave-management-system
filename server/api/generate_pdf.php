<?php
declare(strict_types=1);
/**
 * CAHCET Faculty Leave Management System - PDF Generator
 * Uses mpdf for reliable PDF output.
 */
error_reporting(0);
ini_set('display_errors', '0');

ob_start();

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../SimpleJWT.php';
require_once __DIR__ . '/../audit.php';

ob_clean();

try {
    $token = JWT::get_bearer_token();
    if (!$token) throw new Exception('Unauthorized', 401);

    $user = JWT::decode($token);
    if (!$user) throw new Exception('Invalid token', 401);

    $leaveId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($leaveId <= 0) throw new Exception('Invalid Leave ID', 400);

    $stmt = $conn->prepare(
        "SELECT l.*, u.name, u.role, u.department FROM leave_requests l
         JOIN users u ON l.user_id = u.id WHERE l.id = ?"
    );
    $stmt->execute([$leaveId]);
    $leave = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$leave) throw new Exception('Leave request not found', 404);

    $canView = $user['id'] == $leave['user_id']
        || in_array($user['role'], ['admin', 'principal'])
        || ($user['role'] === 'hod' && $user['department'] === $leave['department']);

    if (!$canView) throw new Exception('Access denied', 403);

    $subs = [];
    $approvals = [];
    try {
        $stmt = $conn->prepare(
            "SELECT ls.*, u.name AS sub_name FROM leave_substitutions ls
             JOIN users u ON ls.substitute_user_id = u.id WHERE ls.leave_request_id = ?"
        );
        $stmt->execute([$leaveId]);
        $subs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {}
    try {
        $stmt = $conn->prepare("SELECT * FROM approvals WHERE leave_request_id = ? ORDER BY created_at ASC");
        $stmt->execute([$leaveId]);
        $approvals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {}

    $hodApproval = $principalApproval = null;
    foreach ($approvals as $app) {
        if ($app['role_at_time'] === 'hod') $hodApproval = $app;
        if ($app['role_at_time'] === 'principal') $principalApproval = $app;
    }

    function getSignerName(PDO $conn, ?array $approval): string {
        if (!$approval) return '';
        try {
            $stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
            $stmt->execute([$approval['approver_id']]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? htmlspecialchars($row['name']) : '';
        } catch (Throwable $e) { return ''; }
    }

    $hodName = getSignerName($conn, $hodApproval);
    $princName = getSignerName($conn, $principalApproval);

    $reason = htmlspecialchars(preg_replace('/\s+/', ' ', trim($leave['reason'] ?? '')));
    $leaveType = htmlspecialchars($leave['leave_type'] ?? '');
    $dept = htmlspecialchars($leave['department'] ?? '-');
    $applicant = htmlspecialchars($leave['name'] ?? '');

    if (!empty($leave['duration_type']) && $leave['duration_type'] === 'Hours') {
        $duration = htmlspecialchars($leave['selected_hours'] ?? '-') . ' Hour(s)';
        $dateStr = date('d-m-Y', strtotime($leave['start_date']));
    } else {
        $d1 = new DateTime($leave['start_date']);
        $d2 = new DateTime($leave['end_date']);
        $days = $d1->diff($d2)->days + 1;
        $duration = $days . ' Day(s)';
        $dateStr = date('d-m-Y', strtotime($leave['start_date'])) . ' to ' . date('d-m-Y', strtotime($leave['end_date']));
    }

    $hodStatus = htmlspecialchars($leave['hod_status'] ?? 'Pending');
    $princStatus = htmlspecialchars($leave['principal_status'] ?? 'Pending');
    $rejReason = htmlspecialchars($leave['rejection_reason'] ?? '');

    $html = '
<style>
body { font-family: DejaVu Sans, sans-serif; font-size: 11pt; }
h1 { text-align: center; font-size: 16pt; margin-bottom: 2px; }
h2 { text-align: center; font-size: 10pt; color: #555; margin-top: 0; margin-bottom: 15px; }
h3 { font-size: 12pt; background: #eee; padding: 5px; margin: 15px 0 8px 0; }
table.info { width: 100%; border-collapse: collapse; }
table.info td { padding: 3px 0; }
table.info td:first-child { font-weight: bold; width: 140px; }
.footer { font-size: 8pt; color: #888; text-align: center; margin-top: 25px; }
</style>

<h1>C. ABDUL HAKEEM COLLEGE OF ENGINEERING & TECHNOLOGY</h1>
<h2>Melvisharam - 632 509</h2>
<h2 style="border-bottom: 1px solid #333; padding-bottom: 8px;">LEAVE APPLICATION</h2>

<h3>1. APPLICANT & LEAVE DETAILS</h3>
<table class="info">
<tr><td>Leave ID:</td><td>#' . $leaveId . '</td></tr>
<tr><td>Applicant:</td><td>' . $applicant . '</td></tr>
<tr><td>Department:</td><td>' . $dept . '</td></tr>
<tr><td>Leave Type:</td><td>' . $leaveType . '</td></tr>
<tr><td>Duration:</td><td>' . $duration . '</td></tr>
<tr><td>Date(s):</td><td>' . $dateStr . '</td></tr>
<tr><td>Reason:</td><td>' . ($reason ?: '-') . '</td></tr>
</table>

<h3>2. APPLICATION</h3>
<p>Respected Sir,</p>
<p>Kindly grant me ' . $leaveType . ' leave for ' . $duration . ' (' . $dateStr . '). Reason: ' . ($reason ?: 'As stated above.') . '</p>
<p>Thanking you,</p>
<p><strong>Yours faithfully,</strong><br>' . $applicant . '</p>';

    if (!empty($subs)) {
        $html .= '<h3>3. SUBSTITUTION ARRANGEMENTS</h3><ul>';
        foreach ($subs as $s) {
            $st = htmlspecialchars($s['status'] ?? 'PENDING');
            $sn = htmlspecialchars($s['sub_name'] ?? '-');
            $html .= '<li>Date: ' . date('d-m-Y', strtotime($s['date'])) . ' | Hour: ' . (int)($s['hour_slot'] ?? 0) . ' | Substitute: ' . $sn . ' | Status: ' . $st . '</li>';
        }
        $html .= '</ul>';
    }

    $html .= '
<h3>4. APPROVAL STATUS</h3>
<table class="info">
<tr><td>HoD Status:</td><td>' . $hodStatus . '</td></tr>' .
($hodName ? '<tr><td>HoD Approved By:</td><td>' . $hodName . '</td></tr>' : '') . '
<tr><td>Principal Status:</td><td>' . $princStatus . '</td></tr>' .
($princName ? '<tr><td>Principal Approved By:</td><td>' . $princName . '</td></tr>' : '') . '
</table>';

    if ($rejReason) {
        $html .= '<p><em>Rejection Note: ' . $rejReason . '</em></p>';
    }

    $html .= '<p class="footer">CAHCET Faculty Leave Management System | Generated: ' . date('d-m-Y H:i:s') . '</p>';

    $mpdf = new \Mpdf\Mpdf(['mode' => 'utf-8', 'format' => 'A4', 'margin_top' => 15, 'margin_bottom' => 18]);
    $mpdf->WriteHTML($html);

    logAudit($conn, $user['id'], 'PDF_DOWNLOAD', ['leave_id' => $leaveId]);

    $pdfContent = $mpdf->OutputBinaryData();
    $filename = 'Leave_Application_' . $leaveId . '.pdf';

    while (ob_get_level()) ob_end_clean();

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($pdfContent));
    header('Cache-Control: private, no-cache, must-revalidate');
    echo $pdfContent;
    exit;

} catch (Throwable $e) {
    while (ob_get_level()) ob_end_clean();
    error_log('[PDF ERROR] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        $code = (is_int($e->getCode()) && $e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['error' => 'PDF generation failed', 'message' => $e->getMessage()]);
    exit;
}
