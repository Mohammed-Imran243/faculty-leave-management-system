<?php
$base_url = "http://localhost/faculty-leave-management-system%20cr/server/api";

function req($endpoint, $method = "GET", $data = null, $token = null) {
    global $base_url;
    $ch = curl_init($base_url . $endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $headers = ["Content-Type: application/json"];
    if ($token) {
        $headers[] = "Authorization: Bearer $token";
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    if ($method === "POST") {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    } elseif ($method === "PUT") {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        if ($data) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        "code" => $httpCode,
        "body" => json_decode($response, true) ?: $response
    ];
}

ini_set('display_errors', 1);
error_reporting(E_ALL);
echo "=== Testing Permission & Outpass Flow ===\n";

$facUser = 'hari';
$hodUser = 'hod';
$princUser = 'principal';

// Let's force reset their password to pass123 for testing
require_once __DIR__ . '/../includes/config.php';
$hash = password_hash('pass123', PASSWORD_BCRYPT);
$conn->exec("UPDATE users SET password = '$hash' WHERE username IN ('$facUser', '$hodUser', '$princUser')");
echo "Reset passwords of $facUser, $hodUser, $princUser to pass123 for test.\n";

// 2. Login as Faculty, HOD, Principal
$facLogin = req("/auth.php/login", "POST", ["username" => $facUser, "password" => "pass123"]);
if ($facLogin['code'] !== 200) die("Faculty Login failed: " . json_encode($facLogin));
$facToken = $facLogin['body']['token'];

$hodLogin = req("/auth.php/login", "POST", ["username" => $hodUser, "password" => "pass123"]);
$hodToken = $hodLogin['body']['token'];

$princLogin = req("/auth.php/login", "POST", ["username" => $princUser, "password" => "pass123"]);
$princToken = $princLogin['body']['token'];

// 3. Faculty Applies for Permission
echo "\n--- Faculty applying for Permission ---\n";
$permRes = req("/permissions.php/apply", "POST", [
    "permission_date" => date("Y-m-d"),
    "start_time" => "09:30",
    "end_time" => "10:30",
    "reason" => "Doctor appointment"
], $facToken);
echo "Status: " . $permRes['code'] . "\n";
print_r($permRes['body']);

// 4. Faculty Tries to Apply for Outpass in same month
echo "\n--- Faculty applying for Outpass (should FAIL) ---\n";
$outRes = req("/outpasses.php/apply", "POST", [
    "outpass_date" => date("Y-m-d"),
    "out_time" => "11:00",
    "reason" => "Bank work"
], $facToken);
echo "Status: " . $outRes['code'] . "\n";
print_r($outRes['body']);

// 5. Get My Permissions to find ID
$myPerms = req("/permissions.php/my", "GET", null, $facToken)['body'];
$permId = $myPerms[0]['id'];
echo "Permission ID: $permId\n";

// 6. HOD Approves
echo "\n--- HOD approves Permission ---\n";
$hodApprove = req("/permissions.php/$permId/approve/hod", "PUT", ["status" => "Approved"], $hodToken);
echo "Status: " . $hodApprove['code'] . "\n";
print_r($hodApprove['body']);

// 7. Principal Approves
echo "\n--- Principal approves Permission ---\n";
$princApprove = req("/permissions.php/$permId/approve/principal", "PUT", ["status" => "Approved"], $princToken);
echo "Status: " . $princApprove['code'] . "\n";
print_r($princApprove['body']);

// 8. Try PDF Gen
echo "\n--- Download PDF ---\n";
$pdfRes = req("/generate_permission_pdf.php?id=$permId", "GET", null, $facToken);
echo "PDF Response Length: " . strlen($pdfRes['body']) . "\n";
echo "PDF valid (starts with %PDF): " . (strpos($pdfRes['body'], "%PDF") === 0 ? "YES" : "NO") . "\n";

echo "\nTests Complete.\n";
