<?php
declare(strict_types=1);

error_reporting(0);
ini_set('display_errors', '0');

ob_start();

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../core/bootstrap.php';
require_once __DIR__ . '/../core/auth_guard.php';

ob_clean();

try {
    $user = $global_user;
    if (!$user) throw new Exception('Unauthorized', 401);

    if (!in_array(strtolower($user['role']), ['hod', 'principal', 'admin'])) {
        throw new Exception('Forbidden: Insufficient privileges', 403);
    }

    $dept = isset($_GET['department']) ? $_GET['department'] : $user['department'];
    $month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
    $year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

    // Security: HOD can only export their own department
    if (strtolower($user['role']) === 'hod' && $dept !== $user['department']) {
        throw new Exception('Access denied: You can only export reports for your own department.', 403);
    }

    // Fetch Records
    $stmt = $conn->prepare("
        SELECT l.*, u.name as faculty_name, u.employee_code
        FROM leave_requests l
        JOIN users u ON l.user_id = u.id
        WHERE u.department = ? 
        AND (
            (MONTH(l.start_date) = ? AND YEAR(l.start_date) = ?) OR
            (MONTH(l.end_date) = ? AND YEAR(l.end_date) = ?)
        )
        ORDER BY l.start_date ASC
    ");
    $stmt->execute([$dept, $month, $year, $month, $year]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $monthName = date('F', mktime(0, 0, 0, $month, 10));
    $title = "Leave Audit Report - " . htmlspecialchars($dept) . " (" . $monthName . " " . $year . ")";

    // HTML Construction
    $html = '
    <html>
    <head>
        <style>
            body { font-family: "Helvetica", sans-serif; font-size: 10pt; color: #333; }
            .header { text-align: center; border-bottom: 2px solid #800000; padding-bottom: 10px; margin-bottom: 20px; }
            .college-name { font-size: 16pt; font-weight: bold; color: #800000; text-transform: uppercase; }
            .report-title { font-size: 14pt; margin-top: 10px; border-bottom: 1px solid #ddd; padding-bottom: 5px; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th { background-color: #f2f2f2; border: 1px solid #ccc; padding: 8px; text-align: left; font-weight: bold; }
            td { border: 1px solid #ccc; padding: 8px; }
            .status-Approved { color: green; font-weight: bold; }
            .status-Rejected { color: red; font-weight: bold; }
            .status-Pending { color: orange; font-weight: bold; }
            .footer { position: fixed; bottom: 0; width: 100%; text-align: center; font-size: 8pt; border-top: 1px solid #ddd; padding-top: 5px; }
        </style>
    </head>
    <body>
        <div class="header">
            <div class="college-name">C Abdul Hakeem College of Engineering & Technology</div>
            <div style="font-size: 10pt; color: #666;">Melvisharam, Vellore - 632 509</div>
            <div class="report-title">' . htmlspecialchars($title) . '</div>
        </div>

        <table>
            <thead>
                <tr>
                    <th width="20%">Faculty Name</th>
                    <th width="15%">Emp Code</th>
                    <th width="15%">Leave Type</th>
                    <th width="25%">Date Range</th>
                    <th width="10%">Days</th>
                    <th width="15%">Status</th>
                </tr>
            </thead>
            <tbody>';

    if (empty($records)) {
        $html .= '<tr><td colspan="6" style="text-align:center; padding: 20px;">No leave records found for this period.</td></tr>';
    } else {
        foreach ($records as $r) {
            $startDate = date('d-m-Y', strtotime($r['start_date']));
            $endDate = date('d-m-Y', strtotime($r['end_date']));
            $range = ($startDate === $endDate) ? $startDate : "$startDate to $endDate";
            
            // Calculate actual days logic simplified for report
            $d1 = new DateTime($r['start_date']);
            $d2 = new DateTime($r['end_date']);
            $days = $d1->diff($d2)->days + 1;
            
            $status = ($r['principal_status'] !== 'Pending') ? $r['principal_status'] : $r['hod_status'];

            $html .= '<tr>
                <td>' . htmlspecialchars($r['faculty_name']) . '</td>
                <td>' . htmlspecialchars($r['employee_code'] ?? 'N/A') . '</td>
                <td>' . htmlspecialchars($r['leave_type']) . '</td>
                <td>' . $range . '</td>
                <td>' . $days . '</td>
                <td><span class="status-' . $status . '">' . $status . '</span></td>
            </tr>';
        }
    }

    $html .= '
            </tbody>
        </table>

        <div style="margin-top: 40px;">
            <table style="border: none;">
                <tr>
                    <td style="border: none; text-align: left; width: 50%;">
                        <br><br>__________________________<br>Head of the Department
                    </td>
                    <td style="border: none; text-align: right; width: 50%;">
                        <br><br>__________________________<br>Principal
                    </td>
                </tr>
            </table>
        </div>

        <div class="footer">
            Generated on ' . date('d-m-Y H:i:s') . ' | CAHCET Faculty Leave Management System
        </div>
    </body>
    </html>';

    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8', 
        'format' => 'A4', 
        'margin_top' => 10,
        'margin_bottom' => 15,
        'margin_left' => 15,
        'margin_right' => 15
    ]);
    
    $mpdf->WriteHTML($html);
    
    $filename = "Leave_Audit_" . str_replace(' ', '_', $dept) . "_" . $monthName . "_" . $year . ".pdf";

    while (ob_get_level()) ob_end_clean();

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $mpdf->Output('', 'S');
    exit;

} catch (Exception $e) {
    while (ob_get_level()) ob_end_clean();
    http_response_code($e->getCode() ?: 500);
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}
?>
