<?php
declare(strict_types=1);

error_reporting(0);
ini_set('display_errors', '0');

ob_start();

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../core/bootstrap.php';
require_once __DIR__ . '/../core/auth_guard.php';
require_once __DIR__ . '/../core/audit.php';

ob_clean();

try {
    $user = $global_user;
    if (!$user) throw new Exception('Unauthorized', 401);

    $leaveId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($leaveId <= 0) throw new Exception('Invalid Leave ID', 400);

    // Fetch Leave & User Details
    try {
        $stmt = $conn->prepare(
            "SELECT l.*, u.name, u.role, u.department FROM leave_requests l
             JOIN users u ON l.user_id = u.id WHERE l.id = ?"
        );
        $stmt->execute([$leaveId]);
        $leave = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        throw new Exception("Error fetching Leave ID $leaveId: " . $e->getMessage());
    }

    if (!$leave) throw new Exception('Leave request not found', 404);

    // Map Role to Designation
    $roleMap = [
        'faculty' => 'Assistant Professor',
        'hod' => 'Head of Department',
        'principal' => 'Principal',
        'admin' => 'Administrator'
    ];
    $designation = isset($roleMap[$leave['role']]) ? $roleMap[$leave['role']] : ucfirst($leave['role']);

    // Strict Access Control: ONLY the request owner can generate/download the PDF
    $canView = ($user['id'] == $leave['user_id']);
    
    if (!$canView) throw new Exception('Access denied', 403);

    // Fetch HOD Name (for "Through HOD")
    $hodName = "The Head of Department"; // Default
    // Fetch HOD Name
    $hodName = "The Head of Department";
    try {
        $stmtHod = $conn->prepare("SELECT name FROM users WHERE LOWER(role) = 'hod' AND department = ? LIMIT 1");
        $stmtHod->execute([$leave['department']]);
        $hod = $stmtHod->fetch(PDO::FETCH_ASSOC);
        if ($hod) $hodName = $hod['name'];
    } catch (Throwable $e) { throw new Exception("Error fetching HOD: " . $e->getMessage()); }

    // Calculate "Leaves Already Availed"
    $availedDays = 0;
    try {
        $stmtAvailed = $conn->prepare("SELECT * FROM leave_requests WHERE user_id = ? AND principal_status = 'Approved' AND id < ?");
        $stmtAvailed->execute([$leave['user_id'], $leaveId]);
        $pastLeaves = $stmtAvailed->fetchAll(PDO::FETCH_ASSOC);
        foreach ($pastLeaves as $pl) {
            if ($pl['duration_type'] === 'Days') {
                $d1 = new DateTime($pl['start_date']);
                $d2 = new DateTime($pl['end_date']);
                $availedDays += $d1->diff($d2)->days + 1;
            } else {
                $availedDays += 0.5; 
            }
        }
    } catch (Throwable $e) { throw new Exception("Error fetching Availed Leaves: " . $e->getMessage()); }

    // Fetch Substitutions
    $subs = [];
    try {
        $stmt = $conn->prepare(
            "SELECT ls.*, u.name AS sub_name FROM leave_substitutions ls
             JOIN users u ON ls.substitute_user_id = u.id WHERE ls.leave_request_id = ? ORDER BY ls.date, ls.hour_slot"
        );
        $stmt->execute([$leaveId]);
        $subs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { throw new Exception("Error fetching Substitutions: " . $e->getMessage()); }

    // Fetch Approvals
    $approvals = [];
    try {
        $stmt = $conn->prepare("SELECT * FROM approvals WHERE leave_request_id = ? ORDER BY created_at ASC");
        $stmt->execute([$leaveId]);
        $approvals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { throw new Exception("Error fetching Approvals: " . $e->getMessage()); }

    $hodApproval = null;
    $principalApproval = null;
    foreach ($approvals as $app) {
        if ($app['role_at_time'] === 'hod') $hodApproval = $app;
        if ($app['role_at_time'] === 'principal') $principalApproval = $app;
    }

    // Signatures
    function getSignerInfo(PDO $conn, ?array $approval): ?array {
        if (!$approval) return null;
        try {
            $stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
            $stmt->execute([$approval['approver_id']]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Throwable $e) { throw new Exception("Error fetching Signer Info: " . $e->getMessage()); }
    }

    $hodInfo = getSignerInfo($conn, $hodApproval);
    $princInfo = getSignerInfo($conn, $principalApproval);
    
    // --- Official Approval Stamps ---
    // User requested "OFFICIAL APPROVAL STAMPS" in DARK GREEN.
    // Rectangular with rounded corners.
    
    // HOD Stamp
    $hodSigImg = '';
    if ($leave['hod_status'] === 'Approved') {
        $hodDate = 'Date: ' . date('d.m.Y', strtotime($hodApproval['created_at'] ?? 'now'));
        $timeStr = date('H:i:s', strtotime($hodApproval['created_at'] ?? 'now'));
        $deptName = strtoupper($leave['department'] ?? 'Dept');
        
        $hodSigImg = '
        <table style="border: 2px solid #006400; color: #006400; font-family: sans-serif; font-weight: bold; text-align: center; width: 180px; border-collapse: separate; border-spacing: 0; border-radius: 4px; box-shadow: inset 0 0 2px #006400;">
            <tr>
                <td style="border-bottom: 2px solid #006400; padding: 5px; font-size: 14pt; letter-spacing: 2px;">APPROVED</td>
            </tr>
            <tr>
                <td style="padding: 5px; font-size: 8pt; line-height: 1.3;">
                    HEAD OF THE DEPARTMENT<br>
                    ' . $deptName . '<br>
                    ' . $hodDate . ' ' . $timeStr . '
                </td>
            </tr>
        </table>';
    }

    // Principal Stamp
    $princSigImg = '';
    if ($leave['principal_status'] === 'Approved') {
         $princDate = 'Date: ' . date('d.m.Y', strtotime($principalApproval['created_at'] ?? 'now'));
         $timeStr = date('H:i:s', strtotime($principalApproval['created_at'] ?? 'now'));
         
         $princSigImg = '
         <table style="border: 2px solid #006400; color: #006400; font-family: sans-serif; font-weight: bold; text-align: center; width: 180px; border-collapse: separate; border-spacing: 0; border-radius: 4px; box-shadow: inset 0 0 2px #006400;">
            <tr>
                <td style="border-bottom: 2px solid #006400; padding: 5px; font-size: 14pt; letter-spacing: 2px;">APPROVED</td>
            </tr>
            <tr>
                <td style="padding: 5px; font-size: 8pt; line-height: 1.3;">
                    PRINCIPAL<br>
                    C. Abdul Hakeem College of Engg & Tech<br>
                    ' . $princDate . ' ' . $timeStr . '
                </td>
            </tr>
         </table>';
    }

    // Formatting Data
    $dateOfApp = date('d.m.Y', strtotime($leave['created_at']));
    $startDate = date('d.m.Y', strtotime($leave['start_date']));
    $endDate = date('d.m.Y', strtotime($leave['end_date']));
    
    // Duration Calculation
    if ($leave['duration_type'] === 'Hours') {
        $durationStr = $leave['selected_hours'] . " Hour(s)";
        $leavePeriodStr = $startDate . " (" . $leave['selected_hours'] . " Hours)";
    } else {
        $d1 = new DateTime($leave['start_date']);
        $d2 = new DateTime($leave['end_date']);
        $days = $d1->diff($d2)->days + 1;
        $durationStr = $days;
        $leavePeriodStr = $startDate;
        if ($days > 1) $leavePeriodStr .= " to " . $endDate;
    }

    // Faculty Signature
    $facultySigImg = '(Signature of Staff)';
    
    // Check if OD / ED
    $upperType = strtoupper($leave['leave_type']);
    $isOD = ($upperType === 'OD' || $upperType === 'ED' || strpos($upperType, 'DUTY') !== false);

    // HTML Construction
    $html = '
    <html>
    <head>
        <style>
            body { font-family: "Times New Roman", serif; font-size: 11pt; color: #000; }
            table { width: 100%; border-collapse: collapse; }
            td, th { vertical-align: top; padding: 2px; }
            
            .college-name { font-size: 16pt; font-weight: bold; text-transform: uppercase; margin-bottom:5px; text-align: center; color: #800000; }
            .college-addr { font-size: 12pt; text-align: center; color: #800000; }
            
            .form-title { 
                font-size: 14pt; 
                font-weight: bold; 
                text-decoration: underline; 
                margin-top: 15px; 
                text-align: center;
                color: #800000;
            }
            
            .content-table { margin-top: 20px; width: 100%; }
            .content-table td { padding: 5px; }
            
            .field-label { font-weight: bold; white-space: nowrap; }
            .field-value { border-bottom: 1px dotted #000; padding-left: 5px; }
            
            .letter-body { margin-top: 20px; line-height: 1.6; text-align: justify; }
            
            .arrangement-table { min-width:100%; margin-top: 15px; border: 1px solid #000; font-size: 10pt; }
            .arrangement-table th { border: 1px solid #000; background: #f0f0f0; padding: 5px; text-align: center; }
            .arrangement-table td { border: 1px solid #000; padding: 5px; text-align: center; }
            
            .footer-sig { margin-top: 40px; width: 100%; }
            .sig-box { text-align: center; width: 45%; vertical-align: bottom; }
            .sig-img-container { height: 75px; display: flex; align-items: flex-end; justify-content: center; }
            
            .footer-text { position: fixed; bottom: 0; width: 100%; text-align: center; font-size: 8pt; color: #666; }
            
            /* OD Specific Styles */
            .od-table { width: 100%; margin-top: 15px; font-size: 11pt; border-collapse: separate; border-spacing: 0 10px; }
            .od-label { font-weight: bold; width: 30%; }
            .od-value { width: 70%; border-bottom: 1px dotted #000; }
        </style>
    </head>
    <body style="padding-top: 10px;">
    
        <!-- Header -->
        <div style="text-align: center; margin-bottom: 5px;">
            <div class="college-name">C ABDUL HAKEEM COLLEGE OF ENGINEERING & TECHNOLOGY</div>
            <div class="college-addr">Melvisharam – 632 509</div>
        </div>
        
        <hr style="height: 1px; border: 0; border-top: 2px solid #800000; margin: 10px 0 15px 0;">
    ';

    if ($isOD) {
        $html .= '
        <div class="form-title">Permission for Other Duty</div>

        <table class="od-table">
            <tr>
                <td class="od-label">From :</td>
                <td class="od-value"><b>' . htmlspecialchars($leave['name'] ?? '') . '</b></td>
            </tr>
            <tr>
                <td class="od-label">Designation :</td>
                <td class="od-value">' . htmlspecialchars($designation ?? '') . '</td>
            </tr>
            <tr>
                <td class="od-label">Department :</td>
                <td class="od-value">' . htmlspecialchars($leave['department'] ?? '') . '</td>
            </tr>
            <tr>
                <td class="od-label">To :</td>
                <td><b>The Correspondent / Principal</b><br><small>C. Abdul Hakeem College of Engg. & Tech.</small></td>
            </tr>
            <tr>
                <td class="od-label">Through :</td>
                <td class="od-value"><b>The Head of Department (' . htmlspecialchars($hodName ?? 'HOD') . ')</b></td>
            </tr>
            <tr>
                <td class="od-label">Date :</td>
                <td class="od-value">' . $dateOfApp . '</td>
            </tr>
            <tr>
                <td class="od-label">Leave Duration / Days :</td>
                <td class="od-value">' . $leavePeriodStr . ' (' . $durationStr . ' days)</td>
            </tr>
            <tr>
                <td class="od-label">Reason (Official Duty) :</td>
                <td class="od-value">' . htmlspecialchars($leave['reason'] ?? '') . '</td>
            </tr>
        </table>

        <div style="margin-top: 30px; overflow: hidden;">
            <div style="float: right; text-align: center; width: 250px;">
                Yours faithfully,<br><br>
                <div style="height: 50px; display: flex; align-items: flex-end; justify-content: center;">' . $facultySigImg . '</div>
                <br>
                <b>' . htmlspecialchars($leave['name']) . '</b>
            </div>
        </div>
        ';
    } else {
        $html .= '
        <div class="form-title">LEAVE APPLICATION</div>

        <!-- Applicant Details -->
        <table class="content-table">
            <tr>
                <td width="10%" class="field-label">From :</td>
                <td width="40%">
                    <div class="field-value" style="font-weight:bold">' . htmlspecialchars($leave['name'] ?? '') . '</div>
                    <div class="field-value">' . htmlspecialchars($designation ?? '') . '</div>
                    <div class="field-value">' . htmlspecialchars($leave['department'] ?? '') . '</div>
                </td>
                <td width="5%"></td>
                <td width="45%">
                     <div style="margin-bottom: 5px;">
                        <b>To :</b> The Correspondent / Principal<br>
                        <span style="padding-left:30px">C. Abdul Hakeem College of Engg. & Tech.</span><br>
                        <span style="padding-left:30px">Melvisharam - 632 509.</span>
                     </div>
                </td>
            </tr>
            <tr>
                <td class="field-label">Through HOD :</td>
                <td>
                    <div class="field-value">' . htmlspecialchars($hodName ?? 'N/A') . '</div>
                </td>
                <td colspan="2" style="text-align: right;">
                    <b>Date :</b> <u>' . $dateOfApp . '</u>
                </td>
            </tr>
        </table>

        <!-- Letter Body -->
        <div class="letter-body">
            Sir,<br>
            <div style="text-indent: 50px; margin-top: 10px;">
                Kindly grant me <b>' . htmlspecialchars($leave['leave_type'] ?? '') . '</b> leave for <b>' . ($durationStr ?? '0') . '</b> day(s) 
                from <b>' . $startDate . '</b> to <b>' . $endDate . '</b>.
            </div>
            <div style="margin-top: 15px;">
                <b>Reason :</b> <u>' . htmlspecialchars($leave['reason'] ?? '') . '</u>
            </div>
            <div style="margin-top: 10px;">
                <b>No. of days leave already availed :</b> <u>' . ($availedDays > 0 ? $availedDays : '_____') . '</u>
            </div>
        </div>

        <div style="margin-top: 20px; overflow: hidden;">
            <div style="float: right; text-align: center; width: 250px;">
                Thanking You,<br><br>
                Yours faithfully,<br><br>
                <div style="height: 50px; display: flex; align-items: flex-end; justify-content: center;">' . $facultySigImg . '</div>
                <br>
                <b>' . htmlspecialchars($leave['name'] ?? '') . '</b>
            </div>
        </div>
        ';
    }

    $html .= '
        <!-- Class Arrangements -->
        <div style="margin-top: 10px; font-weight: bold;">Class arrangements made:</div>
        
        <table class="arrangement-table">
            <thead>
                <tr>
                    <th width="15%">Day & Date</th>
                    <th width="10%">Hour</th>
                    <th width="15%">Class</th>
                    <th width="20%">Subject</th>
                    <th width="25%">Arrangement</th>
                    <th width="15%">Initials of Faculty</th>
                </tr>
            </thead>
            <tbody>';
            
            if (empty($subs)) {
                $html .= '<tr><td colspan="6" style="padding:15px; text-align:center;">No substitution required / arrangements made.</td></tr>';
            } else {
                foreach ($subs as $s) {
                    $html .= '<tr>
                        <td>' . date('D d.m.y', strtotime($s['date'] ?? 'now')) . '</td>
                        <td>' . htmlspecialchars($s['hour_slot'] ?? '-') . '</td>
                        <td>' . htmlspecialchars($s['class_name'] ?? '-') . '</td>
                        <td>' . htmlspecialchars($s['subject_code'] ?? '-') . '</td>
                        <td style="text-align:left; padding-left:10px;">' . htmlspecialchars($s['sub_name'] ?? '-') . '</td>
                        <td>' . (($s['status'] ?? '') === 'ACCEPTED' ? 'Accepted' : ($s['status'] ?? '')) . '</td>
                    </tr>';
                }
            }

    $html .= '
            </tbody>
        </table>

        <!-- Signatures (Split Bottom) -->
        <table class="footer-sig">
            <tr>
                <td class="sig-box" style="text-align: left;">
                    <div style="font-weight: bold; margin-bottom: 10px;">Recommended and Submitted:</div>
                    <div class="sig-img-container" style="justify-content: flex-start; height: auto; margin-bottom: 10px;">' . $hodSigImg . '</div>
                    <div style="border-top: 1px solid #000; width: 80%;">
                        <b>' . ($hodApproval ? htmlspecialchars($hodInfo['name'] ?? 'HOD') : 'HOD Name') . '</b><br>
                        HEAD OF THE DEPARTMENT<br>
                        Date: ' . ($hodApproval ? date('d.m.y', strtotime($hodApproval['created_at'] ?? 'now')) : '') . '
                    </div>
                </td>
                
                <td width="10%"></td> <!-- Spacer -->

                <td class="sig-box" style="text-align: right;">
                    <div style="font-weight: bold; margin-bottom: 10px;">Granted:</div>
                    <div class="sig-img-container" style="justify-content: flex-end; height: auto; margin-bottom: 10px;">' . $princSigImg . '</div>
                    <div style="border-top: 1px solid #000; width: 80%; display: inline-block;">
                        <b>' . ($principalApproval ? htmlspecialchars($princInfo['name'] ?? 'Principal') : 'Principal') . '</b><br>
                        PRINCIPAL<br>
                         C. Abdul Hakeem College<br>
                        Date: ' . ($principalApproval ? date('d.m.y', strtotime($principalApproval['created_at'] ?? 'now')) : '') . '
                    </div>
                </td>
            </tr>
        </table>

        <div class="footer-text">
            Generated by CAHCET Faculty Leave Management System
        </div>

    </body>
    </html>
    ';

    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8', 
        'format' => 'A4', 
        'margin_top' => 10, 
        'margin_bottom' => 10, 
        'margin_left' => 15, 
        'margin_right' => 15
    ]);
    
    $mpdf->WriteHTML($html);
    
    logAudit($conn, $user['id'], 'PDF_DOWNLOAD', ['leave_id' => $leaveId]);

    $pdfContent = $mpdf->OutputBinaryData();
    $filename = 'Leave_Application_' . $leaveId . '.pdf';

    while (ob_get_level()) ob_end_clean();

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($pdfContent));
    echo $pdfContent;
    exit;

} catch (Throwable $e) {
    while (ob_get_level()) ob_end_clean();
    $logFile = __DIR__ . '/../logs/pdf_debug.log';
    file_put_contents($logFile, date('Y-m-d H:i:s') . " [PDF ERROR] " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n\n", FILE_APPEND);
    
    http_response_code(500);
    header('Content-Type: application/json');
    // For debugging, we can return the error details too
    echo json_encode(['error' => 'PDF Generation Failed', 'details' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    exit;
}
?>
