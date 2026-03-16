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

    $permId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($permId <= 0) throw new Exception('Invalid Permission ID', 400);

    // Fetch Permission & User Details
    $stmt = $conn->prepare(
        "SELECT p.*, u.name, u.role, u.department, u.signature_path FROM faculty_permissions p
         JOIN users u ON p.user_id = u.id WHERE p.id = ?"
    );
    $stmt->execute([$permId]);
    $perm = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$perm) throw new Exception('Permission request not found', 404);

    // Map Role to Designation
    $roleMap = [
        'faculty' => 'Assistant Professor',
        'hod' => 'Head of Department',
        'principal' => 'Principal',
        'admin' => 'Administrator'
    ];
    $designation = isset($roleMap[$perm['role']]) ? $roleMap[$perm['role']] : ucfirst($perm['role']);

    // Strict Access Control: ONLY the request owner can generate/download the PDF
    $canView = ($user['id'] == $perm['user_id']);
    
    if (!$canView) throw new Exception('Access denied', 403);
    if ($perm['status'] !== 'Approved') throw new Exception('Permission must be Approved to download PDF', 403);

    // Formatting Data
    $requestDate = date('d.m.Y', strtotime($perm['created_at']));
    $reqDateOfPerm = date('d.m.Y', strtotime($perm['permission_date']));
    $sTime = date('h:i', strtotime($perm['start_time']));
    $eTime = date('h:i', strtotime($perm['end_time']));
    $sMeridiem = date('a', strtotime($perm['start_time']));
    $eMeridiem = date('a', strtotime($perm['end_time']));
    $durationStr = "1 Hour";

    $durationStr = "1 Hour";

    // Faculty Signature
    $facultySigImg = '(Signature of Staff)';
    if (!empty($perm['signature_path'])) {
        $sigPath = __DIR__ . '/../' . $perm['signature_path'];
        if ($perm['signature_path'] && file_exists($sigPath)) {
            $facultySigImg = '<img src="../' . htmlspecialchars($perm['signature_path']) . '" height="60">';
        }
    }
    // --- Official Approval Stamps ---
    // HOD Stamp
    $hodSigImg = '';
    if ($perm['status'] === 'Approved') {
        $hodDate = date('d.m.Y'); // We don't store approval time for permissions yet
        $timeStr = '';
        $deptName = strtoupper($perm['department']);
        
        $hodSigImg = '
        <table style="border: 2px solid #006400; color: #006400; font-family: sans-serif; font-weight: bold; text-align: center; width: 180px; border-collapse: separate; border-spacing: 0; border-radius: 4px; box-shadow: inset 0 0 2px #006400; margin-top:20px;">
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
    if ($perm['status'] === 'Approved') {
         $princDate = date('d.m.Y');
         $timeStr = '';
         
         $princSigImg = '
         <table style="border: 2px solid #006400; color: #006400; font-family: sans-serif; font-weight: bold; text-align: center; width: 180px; border-collapse: separate; border-spacing: 0; border-radius: 4px; box-shadow: inset 0 0 2px #006400; margin-top:20px;">
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

    // HTML Construction
    $html = '
    <html>
    <head>
        <style>
            body { font-family: "Times New Roman", serif; font-size: 12pt; color: #000; }
            table { width: 100%; border-collapse: collapse; }
            td { vertical-align: top; padding: 2px; }
            
            .college-name { font-size: 16pt; font-weight: bold; text-transform: uppercase; margin-bottom:5px; text-align: center; }
            .college-addr { font-size: 12pt; text-align: center; }
            
            .form-title { 
                font-size: 14pt; 
                font-weight: bold; 
                text-decoration: underline; 
                margin-top: 25px; 
                text-align: center;
                color: #800000;
            }
            
            .content-table { margin-top: 15px; width: 100%; }
            .field-label { font-weight: bold; padding-right: 10px; }
            .field-value { border-bottom: 1px dotted #000; }
            
            .letter-body { margin-top: 30px; line-height: 2; text-align: justify; font-size: 13pt; }
            
            .footer-sig { margin-top: 60px; width: 100%; }
            .sig-box { vertical-align: bottom; }
            
            .notes-section { margin-top: 50px; }
            .notes-heading { font-weight: bold; text-decoration: underline; color: #800000; font-size: 12pt; margin-bottom: 10px; }
            .notes-list { margin: 0; padding-left: 20px; line-height: 1.5; font-size: 11pt; }
        </style>
    </head>
    <body style="padding-top: 10px;">
    
        <!-- Header -->
        <div style="text-align: center; margin-bottom: 5px; color: #800000;">
            <div class="college-name">C ABDUL HAKEEM COLLEGE OF ENGINEERING & TECHNOLOGY</div>
            <div class="college-addr">Melvisharam – 632 509</div>
        </div>
        
        <hr style="height: 1px; border: 0; border-top: 1px solid #d3d3d3; margin: 15px 0;">

        <!-- Applicant Details -->
        <table class="content-table" style="margin-bottom: 25px;">
            <tr>
                <td width="10%">From:</td>
                <td width="40%" class="field-value">' . htmlspecialchars($perm['name']) . '</td>
                <td width="5%"></td>
                <td width="45%" rowspan="2">
                     <div style="color: #640000;">
                        To the Correspondent / Principal:<br>
                        <b>C. ABDUL HAKEEM COLLEGE OF<br>ENGINEERING & TECHNOLOGY</b><br>
                        Hakeem Nagar, Melvisharam - 632 509.
                     </div>
                </td>
            </tr>
            <tr>
                <td>Dept :</td>
                <td class="field-value">' . htmlspecialchars($perm['department']) . '</td>
                <td></td>
            </tr>
        </table>

        <table style="width: 100%; border-top: 1px solid #d3d3d3; border-bottom: 1px solid #d3d3d3; padding: 10px 0;">
            <tr>
                <td width="28%">The Head of the Department:</td>
                <td width="42%" style="border-bottom: 1px solid #e2e8f0;">&nbsp;</td>
                <td width="8%" style="text-align: right;">Date :</td>
                <td width="22%" style="border-bottom: 1px solid #e2e8f0; text-align: center;">' . $requestDate . '</td>
            </tr>
        </table>

        <div class="form-title">PERMISSION FOR PERSONAL REASONS</div>

        <!-- Letter Body -->
        <div class="letter-body">
            Sir,<br>
            <div style="text-indent: 50px; margin-top: 15px;">
                Kindly grant me permission for <span style="display:inline-block; border-bottom: 1px solid #e2e8f0; width: 350px;"></span><br>
                on <span style="display:inline-block; border-bottom: 1px solid #e2e8f0; width: 150px; text-align:center;">' . $reqDateOfPerm . '</span> 
                <span style="display:inline-block; border-bottom: 1px solid #e2e8f0; width: 50px; text-align:center;">' . $sTime . '</span> ' . $sMeridiem . ' / pm, 
                <span style="display:inline-block; border-bottom: 1px solid #e2e8f0; width: 50px; text-align:center;">' . $eTime . '</span> ' . $eMeridiem . ' / pm
            </div>
        </div>

        <table class="footer-sig">
            <tr>
                <td class="sig-box" style="text-align: left; width: 50%;">
                    Thanking You,<br><br><br>
                    Date : <span style="display:inline-block; border-bottom: 1px solid #e2e8f0; width: 120px; text-align:center;">' . $requestDate . '</span>
                </td>
                <td class="sig-box" style="text-align: right; width: 50%;">
                    Yours Faithfully,<br><br><br><br>
                    <span style="display:inline-block; border-top: 1px dotted #000; width: 250px; padding-top: 5px; text-align:center;">
                        ' . $facultySigImg . '
                    </span>
                </td>
            </tr>
            <tr>
                <td class="sig-box" style="text-align: left; width: 50%; padding-top:20px;">
                    ' . $hodSigImg . '
                </td>
                <td class="sig-box" style="text-align: right; width: 50%; padding-top:20px;">
                     <div style="float: right;">' . $princSigImg . '</div>
                </td>
            </tr>
        </table>

        <!-- Notes Footer -->
        <div class="notes-section">
            <div class="notes-heading">NOTE :</div>
            <ol class="notes-list">
                <li>Permission cannot be availed when staff member has class work.</li>
                <li>Permission can be availed for the first one hour or last one hour and not in the middle of the day.</li>
                <li>Only two permissions can be availed in one month.</li>
            </ol>
        </div>

    </body>
    </html>
    ';

    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8', 
        'format' => 'A4', 
        'margin_top' => 15, 
        'margin_bottom' => 15, 
        'margin_left' => 15, 
        'margin_right' => 15
    ]);
    
    $mpdf->WriteHTML($html);

    $pdfContent = $mpdf->OutputBinaryData();
    $filename = 'Permission_' . $permId . '.pdf';

    while (ob_get_level()) ob_end_clean();

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($pdfContent));
    echo $pdfContent;
    exit;

} catch (Throwable $e) {
    while (ob_get_level()) ob_end_clean();
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'PDF Generation Failed: ' . $e->getMessage()]);
    exit;
}
