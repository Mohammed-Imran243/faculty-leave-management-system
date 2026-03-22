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

    $requestedDept = isset($_GET['department']) ? $_GET['department'] : null;
    $month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
    $year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

    $dept = null;
    $isGlobal = false;

    if (strtolower($user['role']) === 'hod') {
        // HOD is strictly restricted to their own department from session
        $dept = $user['department'];
    } else {
        // Principal/Admin can specify a department or 'all'
        if (!$requestedDept || strtolower($requestedDept) === 'all') {
            $isGlobal = true;
        } else {
            $dept = $requestedDept;
        }
    }

    // Fetch Records
    if ($isGlobal) {
        $stmt = $conn->prepare("
            SELECT l.*, u.name as faculty_name, u.employee_code, u.department
            FROM leave_requests l
            JOIN users u ON l.user_id = u.id
            WHERE (
                (MONTH(l.start_date) = ? AND YEAR(l.start_date) = ?) OR
                (MONTH(l.end_date) = ? AND YEAR(l.end_date) = ?)
            )
            ORDER BY u.department, l.start_date ASC
        ");
        $stmt->execute([$month, $year, $month, $year]);
    } else {
        $stmt = $conn->prepare("
            SELECT l.*, u.name as faculty_name, u.employee_code, u.department
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
    }
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Audit Logging
    require_once __DIR__ . '/../core/audit.php';
    logAudit($conn, (int)$user['id'], 'DOWNLOAD_REPORT', [
        'is_global' => $isGlobal,
        'department' => $dept ?? 'ALL',
        'month' => $month,
        'year' => $year
    ]);

    $monthName = date('F', mktime(0, 0, 0, $month, 10));
    $reportTarget = $isGlobal ? "All Departments" : htmlspecialchars((string)($dept ?? 'N/A'));
    $title = "Faculty Leave Report - " . $reportTarget . " (" . $monthName . " " . $year . ")";

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
                    <th width="12%">Emp Code</th>
                    <th width="10%">Dept</th>
                    <th width="15%">Leave Type</th>
                    <th width="25%">Date Range</th>
                    <th width="18%">Status (HOD/Pri)</th>
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
                
                $hStatus = $r['hod_status'];
                $pStatus = $r['principal_status'];

                $html .= '<tr>
                    <td>' . htmlspecialchars((string)($r['faculty_name'] ?? 'N/A')) . '</td>
                    <td>' . htmlspecialchars((string)($r['employee_code'] ?? 'N/A')) . '</td>
                    <td>' . htmlspecialchars((string)($r['department'] ?? 'N/A')) . '</td>
                    <td>' . htmlspecialchars((string)($r['leave_type'] ?? 'N/A')) . '</td>
                    <td>' . $range . '</td>
                    <td>
                        <span class="status-' . $hStatus . '">' . $hStatus . '</span> / 
                        <span class="status-' . $pStatus . '">' . $pStatus . '</span>
                    </td>
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
    
    if ($isGlobal) {
        $filename = "all_faculty_leave_report_" . date('Y-m-d') . ".pdf";
    } else {
        $filename = "department_leave_report_" . str_replace(' ', '_', (string)$dept) . "_" . date('Y-m-d') . ".pdf";
    }

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
