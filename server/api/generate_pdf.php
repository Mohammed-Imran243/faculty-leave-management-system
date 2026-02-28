<?php
declare(strict_types=1);

error_reporting(0);
ini_set('display_errors', '0');

ob_start();

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../libs/SimpleJWT.php';
require_once __DIR__ . '/../libs/audit.php';

ob_clean();

try {
    $token = JWT::get_bearer_token();
    if (!$token) throw new Exception('Unauthorized', 401);

    $user = JWT::decode($token);
    if (!$user) throw new Exception('Invalid token', 401);

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

    // Access Control
    $canView = $user['id'] == $leave['user_id']
        || in_array($user['role'], ['admin', 'principal'])
        || ($user['role'] === 'hod' && $user['department'] === $leave['department']);
    
    if (!$canView) throw new Exception('Access denied', 403);

    // Fetch HOD Name (for "Through HOD")
    $hodName = "The Head of Department"; // Default
    // Fetch HOD Name
    $hodName = "The Head of Department";
    try {
        $stmtHod = $conn->prepare("SELECT name FROM users WHERE role = 'hod' AND department = ? LIMIT 1");
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
            $stmt = $conn->prepare("SELECT name, signature_path FROM users WHERE id = ?");
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
        $hodDate = 'Date: ' . date('d.m.Y', strtotime($hodApproval['created_at']));
        $deptName = strtoupper($leave['department']);
        
        $hodSigImg = '
        <table style="border: 3px solid #006400; color: #006400; font-family: sans-serif; font-weight: bold; text-align: center; width: 180px; border-collapse: separate; border-spacing: 0; border-radius: 8px;">
            <tr>
                <td style="border-bottom: 1px solid #006400; padding: 5px; font-size: 14pt;">APPROVED</td>
            </tr>
            <tr>
                <td style="padding: 5px; font-size: 9pt; line-height: 1.2;">
                    HEAD OF THE DEPARTMENT<br>
                    ' . $deptName . '<br>
                    ' . $hodDate . '
                </td>
            </tr>
        </table>';
    }

    // Principal Stamp
    $princSigImg = '';
    if ($leave['principal_status'] === 'Approved') {
         $princDate = 'Date: ' . date('d.m.Y', strtotime($principalApproval['created_at']));
         
         $princSigImg = '
         <table style="border: 3px solid #006400; color: #006400; font-family: sans-serif; font-weight: bold; text-align: center; width: 180px; border-collapse: separate; border-spacing: 0; border-radius: 8px;">
            <tr>
                <td style="border-bottom: 1px solid #006400; padding: 5px; font-size: 14pt;">APPROVED</td>
            </tr>
            <tr>
                <td style="padding: 5px; font-size: 8pt; line-height: 1.2;">
                    PRINCIPAL<br>
                    C. Abdul Hakeem College of Engg & Tech<br>
                    ' . $princDate . '
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

    // Logo Path
    $logoPath = __DIR__ . '/../../client/header_logo.png'; 
    
    // HTML Construction
    $html = '
    <html>
    <head>
        <style>
            body { font-family: "Times New Roman", serif; font-size: 11pt; color: #000; }
            table { width: 100%; border-collapse: collapse; }
            td, th { vertical-align: top; padding: 2px; }
            
            .header-table td { vertical-align: middle; text-align: center; }
            .college-name { font-size: 14pt; font-weight: bold; text-transform: uppercase; }
            .college-addr { font-size: 10pt; }
            .form-title { font-size: 12pt; font-weight: bold; text-decoration: underline; margin-top: 10px; }
            
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
            .sig-img-container { height: 60px; display: flex; align-items: flex-end; justify-content: center; }
            
            .footer-text { position: fixed; bottom: 0; width: 100%; text-align: center; font-size: 8pt; color: #666; }
        </style>
    </head>
    <body>
    
        <!-- Header -->
        <table class="header-table">
            <tr>
                <td width="15%" style="text-align: left;">
                    <img src="' . $logoPath . '" width="80">
                </td>
                <td width="85%">
                    <div class="college-name">C. ABDUL HAKEEM COLLEGE OF ENGINEERING & TECHNOLOGY</div>
                    <div class="college-addr">Melvisharam - 632 509.</div>
                    <div class="form-title">LEAVE APPLICATION</div>
                </td>
            </tr>
        </table>
        
        <hr style="height: 1px; border: 0; border-top: 2px solid #000; margin: 5px 0 15px 0;">

        <!-- Applicant Details -->
        <table class="content-table">
            <tr>
                <td width="10%" class="field-label">From :</td>
                <td width="40%">
                    <div class="field-value" style="font-weight:bold">' . htmlspecialchars($leave['name']) . '</div>
                    <div class="field-value">' . htmlspecialchars($designation) . '</div>
                    <div class="field-value">' . htmlspecialchars($leave['department']) . '</div>
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
                    <div class="field-value">' . htmlspecialchars($hodName) . '</div>
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
                Kindly grant me <b>' . htmlspecialchars($leave['leave_type']) . '</b> leave for <b>' . $durationStr . '</b> day(s) 
                from <b>' . $startDate . '</b> to <b>' . $endDate . '</b>.
            </div>
            <div style="margin-top: 15px;">
                <b>Reason :</b> <u>' . htmlspecialchars($leave['reason']) . '</u>
            </div>
            <div style="margin-top: 10px;">
                <b>No. of days leave already availed :</b> <u>' . ($availedDays > 0 ? $availedDays : '_____') . '</u>
            </div>
        </div>

        <div style="margin-top: 20px; overflow: hidden;">
            <div style="float: right; text-align: center; width: 200px;">
                Thanking You,<br><br>
                Yours faithfully,<br><br>
                <b>' . htmlspecialchars($leave['name']) . '</b>
            </div>
        </div>

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
                    $sDate = date('D d.m.y', strtotime($s['date']));
                    $sSub = htmlspecialchars($s['sub_name']);
                    $status = ($s['status'] === 'ACCEPTED') ? 'Accepted' : $s['status'];
                    $html .= '<tr>
                        <td>' . $sDate . '</td>
                        <td>' . $s['hour_slot'] . '</td>
                        <td>' . htmlspecialchars($s['class_name'] ?? '-') . '</td>
                        <td>' . htmlspecialchars($s['subject_code'] ?? '-') . '</td>
                        <td style="text-align:left; padding-left:10px;">' . $sSub . '</td>
                        <td>' . $status . '</td>
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
                        <b>' . ($hodApproval ? htmlspecialchars($hodInfo['name']) : 'HOD Name') . '</b><br>
                        HEAD OF THE DEPARTMENT<br>
                        Date: ' . ($hodApproval ? date('d.m.y', strtotime($hodApproval['created_at'])) : '') . '
                    </div>
                </td>
                
                <td width="10%"></td> <!-- Spacer -->

                <td class="sig-box" style="text-align: right;">
                    <div style="font-weight: bold; margin-bottom: 10px;">Granted:</div>
                    <div class="sig-img-container" style="justify-content: flex-end; height: auto; margin-bottom: 10px;">' . $princSigImg . '</div>
                    <div style="border-top: 1px solid #000; width: 80%; display: inline-block;">
                        <b>' . ($principalApproval ? htmlspecialchars($princInfo['name']) : 'Principal') . '</b><br>
                        PRINCIPAL<br>
                         C. Abdul Hakeem College<br>
                        Date: ' . ($principalApproval ? date('d.m.y', strtotime($principalApproval['created_at'])) : '') . '
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
