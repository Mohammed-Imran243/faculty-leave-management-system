<?php
// Test Script for Policy Engine
$baseUrl = "http://127.0.0.1/faculty-system/server/api";

// Try to auto-detect base URL
if (!@file_get_contents($baseUrl . "/auth.php/login")) {
    $baseUrl = "http://localhost/faculty-system/server/api";
    // Also try another known URL
    $ch = curl_init("http://127.0.0.1/faculty-leave-management-system%20cr/server/api/auth.php/login");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($code !== 404) {
         $baseUrl = "http://127.0.0.1/faculty-leave-management-system%20cr/server/api";
    }
}

function req($url, $method = 'GET', $data = null, $token = null) {
    global $baseUrl;
    $ch = curl_init($baseUrl . $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    
    $headers = ['Content-Type: application/json'];
    if ($token) $headers[] = "Authorization: Bearer $token";
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    if ($data) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    curl_close($ch);
    
    return ['code' => $code, 'body' => json_decode($response, true) ?? $response];
}

function assertEq($actual, $expected, $msg) {
    if ($actual !== $expected) {
        echo "[FAIL] $msg: Expected '$expected', got '$actual'\n";
        exit(1);
    }
    echo "[PASS] $msg\n";
}

// 1. Setup Users
echo "--- Setting up Users ---\n";
// Create Faculty
$ts = time();
$facultyUsername = "fac_pol_" . $ts;
$facultyEmail = "test_fac_pol_" . $ts . "@test.com";
$res = req("/auth.php/register", "POST", [
    "name" => "Test Faculty Pol", "username" => $facultyUsername, "email" => $facultyEmail, "password" => "123456", "role" => "faculty", "department" => "CSE"
]);
assertEq($res['code'], 200, "Register Faculty");

// Create Admin (make sure we have one)
$adminUsername = "admin_pol_" . $ts;
$adminEmail = "admin_pol_" . $ts . "@test.com";
$res = req("/auth.php/register", "POST", [
    "name" => "Test Admin Pol", "username" => $adminUsername, "email" => $adminEmail, "password" => "admin123", "role" => "admin", "department" => "Administration"
]);
assertEq($res['code'], 200, "Register Admin");

// 2. Login
echo "\n--- Logging In ---\n";
$res = req("/auth.php/login", "POST", ["username" => $facultyUsername, "password" => "123456"]);
$facToken = $res['body']['token'] ?? null;
$facId = $res['body']['user']['id'] ?? null;
assertEq(isset($facToken), true, "Faculty Login");

$res = req("/auth.php/login", "POST", ["username" => $adminUsername, "password" => "admin123"]);
$adminToken = $res['body']['token'] ?? null;
assertEq(isset($adminToken), true, "Admin Login");


// 3. Apply 13 days of Casual Leave as Faculty (Should Fail)
echo "\n--- Test Faculty applying for 13 days of Casual Leave ---\n";
$start_date = date('Y-m-d');
$end_date = date('Y-m-d', strtotime('+12 days')); // 13 days total

$leaveData = [
    "leave_type" => "Casual Leave",
    "start_date" => $start_date,
    "end_date" => $end_date,
    "reason" => "Policy Test Fail"
];
$res = req("/leaves.php/apply", "POST", $leaveData, $facToken);
assertEq($res['code'], 400, "Faculty Apply (Expect 400)");
if (is_array($res['body']) && isset($res['body']['error'])) {
    if (strpos($res['body']['error'], 'Policy Limit Exceeded') !== false) {
        echo "[PASS] Error message matches 'Policy Limit Exceeded'\n";
    } else {
        echo "[FAIL] Error message was: " . $res['body']['error'] . "\n";
        exit(1);
    }
} else {
    echo "[FAIL] Unexpected response format: ".print_r($res['body'],true)."\n";
    exit(1);
}

// 4. Apply 13 days of Casual Leave as Admin with is_override (Should Succeed)
echo "\n--- Test Admin applying for 13 days with is_override ---\n";
$leaveDataAdmin = [
    "user_id" => $facId,
    "is_override" => true,
    "leave_type" => "Casual Leave",
    "start_date" => $start_date,
    "end_date" => $end_date,
    "reason" => "Policy Test Override"
];
$res = req("/leaves.php/apply", "POST", $leaveDataAdmin, $adminToken);
if ($res['code'] !== 200) {
     echo "[FAIL] Admin Override Failed. Response: " . print_r($res, true) . "\n";
     exit(1);
}
assertEq($res['code'], 200, "Admin Apply with Override (Expect 200)");

echo "\nAll Tests Passed Successfully!\n";
?>
