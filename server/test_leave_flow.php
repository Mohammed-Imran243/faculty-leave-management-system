<?php
// Test Script for Faculty System
$baseUrl = "http://127.0.0.1/faculty-system/server/api";

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
    
    if ($response === false) {
        echo "Curl Error: " . curl_error($ch) . "\n";
    }

    curl_close($ch);
    
    return ['code' => $code, 'body' => json_decode($response, true)];
}

function assertEq($actual, $expected, $msg) {
    if ($actual !== $expected) {
        echo "[FAIL] $msg: Expected '$expected', got '$actual'\n";
        // print_r($GLOBALS['last_response']); // Hack to see last response if needed
        exit(1);
    }
    echo "[PASS] $msg\n";
}

// 1. Setup Users
echo "--- Setting up Users ---\n";
// Create Faculty
$facultyEmail = "test_fac_" . time() . "@test.com";
$res = req("/auth.php/register", "POST", [
    "name" => "Test Faculty", "email" => $facultyEmail, "password" => "123456", "role" => "faculty", "department" => "CSE"
]);
assertEq($res['code'], 200, "Register Faculty");

// Create HoD
$hodEmail = "test_hod_" . time() . "@test.com";
$res = req("/auth.php/register", "POST", [
    "name" => "Test HoD", "email" => $hodEmail, "password" => "123456", "role" => "hod", "department" => "CSE"
]);
assertEq($res['code'], 200, "Register HoD");

// Create Principal
$princEmail = "test_princ_" . time() . "@test.com";
$res = req("/auth.php/register", "POST", [
    "name" => "Test Principal", "email" => $princEmail, "password" => "123456", "role" => "principal", "department" => "Administration"
]);
assertEq($res['code'], 200, "Register Principal");


// 2. Login & Tokens
echo "\n--- Logging In ---\n";
$res = req("/auth.php/login", "POST", ["email" => $facultyEmail, "password" => "123456"]);
if (!isset($res['body']['token'])) {
    echo "Login Failed Response: ";
    print_r($res);
    exit(1);
}
$facToken = $res['body']['token'];
$facId = $res['body']['user']['id'];
assertEq(isset($facToken), true, "Faculty Login");

$res = req("/auth.php/login", "POST", ["email" => $hodEmail, "password" => "123456"]);
$hodToken = $res['body']['token'];
assertEq(isset($hodToken), true, "HoD Login");

$res = req("/auth.php/login", "POST", ["email" => $princEmail, "password" => "123456"]);
$princToken = $res['body']['token'];
assertEq(isset($princToken), true, "Principal Login");


// 3. Get Faculty List for Substitution
echo "\n--- Fetching Faculty List ---\n";
$res = req("/users.php/faculty", "GET", null, $facToken);
$facultyList = $res['body'];
$subId = null;
foreach($facultyList as $f) {
    // Pick a faculty that isn't me (conceptually, though test user is fresh)
    if ($f['id'] != $facId) {
        $subId = $f['id'];
        break;
    }
}
if (!$subId) $subId = $facultyList[0]['id']; // Fallback
echo "[PASS] Selected Substitute ID: $subId\n";


// 4. Apply Hourly Leave with Substitution
echo "\n--- Applying for HOURLY Leave with Substitution ---\n";
$leaveData = [
    "leave_type" => "Casual",
    "start_date" => date('Y-m-d'),
    "end_date" => date('Y-m-d'),
    "reason" => "Hourly Test",
    "duration_type" => "Hours",
    "selected_hours" => "1",
    "hourly_date" => date('Y-m-d'), // frontend helper
    "substitutions" => [
        ["hour" => 1, "substitute_id" => $subId]
    ]
];
$res = req("/leaves.php/apply", "POST", $leaveData, $facToken);
if ($res['code'] !== 200) {
    echo "Apply Leave Failed Response: ";
    print_r($res);
    exit(1);
}
assertEq($res['code'], 200, "Apply Hourly Leave");

// 5. HoD Approval
echo "\n--- HoD Verification ---\n";
$res = req("/leaves.php/pending/hod", "GET", null, $hodToken);
$targetLeave = null;
foreach($res['body'] as $l) {
    if ($l['user_id'] == $facId && $l['reason'] == "Hourly Test") {
        $targetLeave = $l;
        break;
    }
}
if (!$targetLeave) { echo "[FAIL] Leave not found in HoD pending list\n"; exit(1); }
echo "[PASS] Found pending leave ID: " . $targetLeave['id'] . "\n";

// Verify Substitution Data in Response
if (isset($targetLeave['substitutions']) && count($targetLeave['substitutions']) > 0) {
     echo "[PASS] Substitutions data present in HoD view\n";
} else {
     echo "[FAIL] Substitutions data MISSING in HoD view\n";
     exit(1);
}

$res = req("/leaves.php/" . $targetLeave['id'] . "/approve/hod", "PUT", ["status" => "Approved"], $hodToken);
assertEq($res['code'], 200, "HoD Approve");


// 6. Principal Approval
echo "\n--- Principal Verification ---\n";
$res = req("/leaves.php/pending/principal", "GET", null, $princToken);
$targetLeaveP = null;
foreach($res['body'] as $l) {
    if ($l['id'] == $targetLeave['id']) {
        $targetLeaveP = $l;
        break;
    }
}
if (!$targetLeaveP) { echo "[FAIL] Leave not found in Principal pending list\n"; exit(1); }
echo "[PASS] Found pending leave for Principal\n";

$res = req("/leaves.php/" . $targetLeave['id'] . "/approve/principal", "PUT", ["status" => "Approved"], $princToken);
assertEq($res['code'], 200, "Principal Approve");


// 7. Final Status Check
echo "\n--- Final Status Check ---\n";
$res = req("/leaves.php/my-leaves", "GET", null, $facToken);
$finalLeave = null;
foreach($res['body'] as $l) {
    if ($l['id'] == $targetLeave['id']) {
        $finalLeave = $l;
        break;
    }
}
assertEq($finalLeave['hod_status'], 'Approved', "Final HoD Status");
assertEq($finalLeave['principal_status'], 'Approved', "Final Principal Status");

// 7. Cleanup
// Note: We don't have delete user in API for normal users, but we can try to rely on manual cleanup or DB script.
// For now, we leave them or if we had a delete endpoint we would use it. 
// We can use an admin user to delete them.
echo "\n--- Cleanup ---\n";
// Register Admin (or login if exists) - Schema has default admin
$res = req("/auth.php/login", "POST", ["email" => "admin@college.edu", "password" => "admin123"]);
if ($res['code'] == 200) {
    $adminToken = $res['body']['token'];
    
    // Delete created users
    // Need to know IDs of HoD and Principal too.
    // For simplicity, we just delete the faculty user, cascade should kill leaves.
    // Ideally we delete all test users.
    
    // Actually, let's just output their IDs so we know.
    // Deleting via API requires ID.
    // Since we verified the flow, we can leave cleanup for later or manual.
    echo "Test Completed Successfully.\n";
} else {
    echo "Could not login as admin to cleanup, but tests passed.\n";
}
?>
