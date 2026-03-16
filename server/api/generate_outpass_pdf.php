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

    $outpassId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($outpassId <= 0) throw new Exception('Invalid Outpass ID', 400);

    // Fetch Outpass Details
    $stmt = $conn->prepare(
        "SELECT o.*, u.name, u.role, u.department, u.signature_path FROM faculty_outpasses o
         JOIN users u ON o.user_id = u.id WHERE o.id = ?"
    );
    $stmt->execute([$outpassId]);
    $outpass = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$outpass) throw new Exception('Outpass request not found', 404);

    // Strict Access Control: ONLY the request owner can generate/download the PDF
    $canView = ($user['id'] == $outpass['user_id']);
    
    if (!$canView) throw new Exception('Access denied', 403);
    if ($outpass['status'] !== 'Approved') throw new Exception('Outpass must be Approved to download PDF', 403);

    // Formatting Data
    $requestDate = date('d.m.Y', strtotime($outpass['created_at']));
    $reqDateOfPass = date('d.m.Y', strtotime($outpass['outpass_date']));
    $oTime = date('h:i A', strtotime($outpass['out_time']));
    $iTime = $outpass['in_time'] ? date('h:i A', strtotime($outpass['in_time'])) : 'N/A';

    $iTime = $outpass['in_time'] ? date('h:i A', strtotime($outpass['in_time'])) : 'N/A';

    // Faculty Signature
    $facultySigImg = 'Signature of Faculty';
    if (!empty($outpass['signature_path'])) {
        $sigPath = __DIR__ . '/../' . $outpass['signature_path'];
        if ($outpass['signature_path'] && file_exists($sigPath)) {
            $facultySigImg = '<img src="../' . htmlspecialchars($outpass['signature_path']) . '" height="60">';
        }
    }

    // --- Official Approval Stamps ---
    // HOD Stamp
    $hodSigImg = '';
    if ($outpass['status'] === 'Approved') {
        $hodDate = date('d.m.Y');
        $timeStr = '';
        $deptName = strtoupper($outpass['department']);
        
        $hodSigImg = '
        <table style="border: 2px solid #006400; color: #006400; font-family: sans-serif; font-weight: bold; text-align: center; width: 160px; border-collapse: separate; border-spacing: 0; border-radius: 4px; box-shadow: inset 0 0 2px #006400; margin-top:10px;">
            <tr>
                <td style="border-bottom: 2px solid #006400; padding: 5px; font-size: 13pt; letter-spacing: 2px;">APPROVED</td>
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
    if ($outpass['status'] === 'Approved') {
         $princDate = date('d.m.Y');
         $timeStr = '';
         
         $princSigImg = '
         <table style="border: 2px solid #006400; color: #006400; font-family: sans-serif; font-weight: bold; text-align: center; width: 160px; border-collapse: separate; border-spacing: 0; border-radius: 4px; box-shadow: inset 0 0 2px #006400; margin-top:10px;">
            <tr>
                <td style="border-bottom: 2px solid #006400; padding: 5px; font-size: 13pt; letter-spacing: 2px;">APPROVED</td>
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
            body { font-family: "Times New Roman", serif; font-size: 14pt; color: #000; }
            table { width: 100%; border-collapse: collapse; }
            td { vertical-align: top; padding: 5px; }
            
            .header-table td { vertical-align: middle; text-align: center; }
            .college-name { font-size: 18pt; font-weight: bold; text-transform: uppercase; margin-bottom:5px; text-align: center; }
            .college-addr { font-size: 14pt; text-align: center; }
            
            .form-title { 
                font-size: 18pt; 
                font-weight: bold;  
                margin-top: 10px; 
                text-align: center;
                color: #800000;
            }
            .form-subtitle {
                font-size: 14pt;
                text-align: center;
                margin-bottom: 20px;
                color: #800000;
            }
            
            .form-fields { width: 100%; margin-top: 20px; border-collapse: separate; border-spacing: 0 15px; }
            .field-label { width: 25%; font-size: 14pt; font-weight: bold; }
            .field-col { width: 5%; text-align: center; font-weight: bold; }
            .field-value { width: 70%; border-bottom: 1px dotted #000; font-size: 14pt; padding-left: 10px; }
            
            .signatures { margin-top: 15px; width: 100%; table-layout: fixed; }
            .signatures td { text-align: center; vertical-align: bottom; }
            
            .security-section { margin-top: 15px; border-top: 1px solid #000; padding-top: 10px; }
            .security-title { font-size: 13pt; font-weight: bold; text-decoration: underline; margin-bottom: 10px; color: #800000; }
            
            .security-table { width: 100%; margin-top: 10px; border-collapse: separate; border-spacing: 0 15px; }
            .sec-label { font-size: 14pt; white-space: nowrap; width: 15%; font-weight: bold; }
            .sec-line { border-bottom: 1px dotted #000; width: 40%; }
            .sec-right { text-align: right; width: 45%; }
            .sec-sig { border-top: 1px dotted #000; display: inline-block; padding-top: 5px; }
        </style>
    </head>
    <body style="padding: 15px;">
    
        <!-- Header -->
        <div style="text-align: center; margin-bottom: 5px;">
            <div class="college-name">C ABDUL HAKEEM COLLEGE OF ENGINEERING & TECHNOLOGY</div>
            <div class="college-addr">Melvisharam – 632 509</div>
        </div>
        
        <hr style="height: 1px; border: 0; border-top: 2px solid #800000; margin: 10px 0;">

        <div class="form-title">OUT PASS FOR FACULTY</div>
        <div class="form-subtitle">(During working hours)</div>
        
        <hr style="height: 1px; border: 0; border-top: 1px solid #d3d3d3; margin: 5px 0;">

        <table class="form-fields">
            <tr>
                <td class="field-label">Department</td>
                <td class="field-col">:</td>
                <td class="field-value">' . htmlspecialchars($outpass['department']) . '</td>
            </tr>
            <tr>
                <td class="field-label">Date</td>
                <td class="field-col">:</td>
                <td class="field-value">' . $reqDateOfPass . '</td>
            </tr>
            <tr>
                <td class="field-label">Exit Time</td>
                <td class="field-col">:</td>
                <td class="field-value">' . $oTime . '</td>
            </tr>
            <tr>
                <td class="field-label">Expected Return</td>
                <td class="field-col">:</td>
                <td class="field-value">' . $iTime . '</td>
            </tr>
            <tr>
                <td class="field-label">Reason</td>
                <td class="field-col">:</td>
                <td class="field-value">' . htmlspecialchars($outpass['reason']) . '</td>
            </tr>
        </table>
        
        <hr style="height: 1px; border: 0; border-top: 1px solid #d3d3d3; margin: 10px 0 0 0;">

        <table class="signatures">
            <tr>
                <td style="text-align: left; width: 35%; padding-left: 20px;">
                    ' . $hodSigImg . '
                </td>
                <td style="text-align: center; width: 35%;">
                    ' . $princSigImg . '
                </td>
                <td style="width: 30%;">
                    <br><br>
                     <span style="display:inline-block; border-top: 1px dotted #000; padding: 5px 20px 0 20px; font-weight: bold; min-height: 40px;">
                        ' . $facultySigImg . '
                     </span>
                </td>
            </tr>
        </table>

        <!-- Security Section -->
        <div class="security-section">
            <table class="security-table">
                <tr>
                    <td colspan="3"><span class="security-title">For Security use :</span></td>
                </tr>
                <tr>
                    <td class="sec-label">TIME OUT :</td>
                    <td class="sec-line"></td>
                    <td class="sec-right"></td>
                </tr>
                <tr>
                    <td class="sec-label">IN :</td>
                    <td class="sec-line"></td>
                    <td class="sec-right">
                        <span style="display:inline-block; border-bottom: 0;">
                            Signature of the Security
                        </span>
                        <span> . . . . . . </span>
                    </td>
                </tr>
            </table>
        </div>

    </body>
    </html>
    ';

    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8', 
        'format' => 'A4', 
        'orientation' => 'P',
        'margin_top' => 10, 
        'margin_bottom' => 10, 
        'margin_left' => 15, 
        'margin_right' => 15,
        'autoPageBreak' => false // CRITICAL: Stop auto page breaks
    ]);
    
    // Scale everything down naturally so it fits exactly on one page
    $html = '<div style="font-size: 0.95em;">' . $html . '</div>';
    
    $mpdf->WriteHTML($html);

    $pdfContent = $mpdf->OutputBinaryData();
    $filename = 'Outpass_' . $outpassId . '.pdf';

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
