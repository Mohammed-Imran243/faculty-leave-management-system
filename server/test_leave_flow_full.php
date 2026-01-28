<?php
// Test Script for Faculty System Full Workflow (Username Based)
$baseUrl = "http://127.0.0.1:8000/server/api";
$last_response = null;

function req($url, $method = 'GET', $data = null, $token = null) {
    global $baseUrl, $last_response;
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
    
    $decoded = json_decode($response, true);
    $last_response = $decoded ? $decoded : $response;
    return ['code' => $code, 'body' => $last_response];
}

function assertEq($actual, $expected, $msg) {
    if ($actual !== $expected) {
        echo "[FAIL] $msg: Expected '$expected', got '$actual'\n";
        echo "Response Body: ";
        print_r($GLOBALS['last_response']);
        exit(1);
    }
    echo "[PASS] $msg\n";
}

echo "--- 1. Setup Users ---\n";
$ts = time();
$dept = "CSE_TEST_$ts";

// Create Requester Faculty
$facUser = "fac1_$ts";
$res = req("/auth.php/register", "POST", ["name" => "Requester", "username" => $facUser, "email" => "fac1_$ts@test.com", "password" => "123", "role" => "faculty", "department" => $dept]);
assertEq($res['code'], 200, "Register Requester");

// Create Substitute Faculty
$subUser = "sub1_$ts";
$res = req("/auth.php/register", "POST", ["name" => "Substitute", "username" => $subUser, "email" => "sub1_$ts@test.com", "password" => "123", "role" => "faculty", "department" => $dept]);
assertEq($res['code'], 200, "Register Substitute");

// Create HoD
$hodUser = "hod_$ts";
$res = req("/auth.php/register", "POST", ["name" => "HoD", "username" => $hodUser, "email" => "hod_$ts@test.com", "password" => "123", "role" => "hod", "department" => $dept]);
assertEq($res['code'], 200, "Register HoD");

// Create Principal
$princUser = "princ_$ts";
$res = req("/auth.php/register", "POST", ["name" => "Principal", "username" => $princUser, "email" => "princ_$ts@test.com", "password" => "123", "role" => "principal", "department" => "Admin"]);
assertEq($res['code'], 200, "Register Principal");


echo "\n--- 2. Logins ---\n";
// Login Requester
$res = req("/auth.php/login", "POST", ["username" => $facUser, "password" => "123"]);
assertEq($res['code'], 200, "Requester Login");
$facToken = $res['body']['token'];

// Login Substitute
$res = req("/auth.php/login", "POST", ["username" => $subUser, "password" => "123"]);
assertEq($res['code'], 200, "Substitute Login");
$subToken = $res['body']['token'];
$subId = $res['body']['user']['id'];

// Login HoD
$res = req("/auth.php/login", "POST", ["username" => $hodUser, "password" => "123"]);
assertEq($res['code'], 200, "HoD Login");
$hodToken = $res['body']['token'];

// Login Principal
$res = req("/auth.php/login", "POST", ["username" => $princUser, "password" => "123"]);
assertEq($res['code'], 200, "Principal Login");
$princToken = $res['body']['token'];


echo "\n--- 3. Apply Leave with Substitute ---\n";
$leaveData = [
    "leave_type" => "Casual",
    "start_date" => date('Y-m-d'),
    "end_date" => date('Y-m-d'),
    "reason" => "Test Leave Full Flow",
    "substitutions" => [
        ["substitute_id" => $subId]
    ]
];
$res = req("/leaves.php/apply", "POST", $leaveData, $facToken);
assertEq($res['code'], 200, "Apply Leave");


echo "\n--- 4. Substitute Verification ---\n";
// Check Pendings
$res = req("/leaves.php/substitutions/pending", "GET", null, $subToken);
$subRequestId = null;
if (count($res['body']) > 0) {
    foreach($res['body'] as $r) {
        if ($r['reason'] == "Test Leave Full Flow") {
            $subRequestId = $r['id'];
            break;
        }
    }
}

if ($subRequestId) {
    echo "[PASS] Substitute sees pending request\n";
    // Accept
    $res = req("/leaves.php/substitutions/$subRequestId/respond", "PUT", ["status" => "ACCEPTED"], $subToken);
    assertEq($res['code'], 200, "Substitute Accept");
} else {
    echo "[FAIL] Substitute did not see request\n";
    print_r($res);
    exit(1);
}


echo "\n--- 5. HoD Verification ---\n";
// HoD should see it now
$res = req("/leaves.php/pending/hod", "GET", null, $hodToken);
$targetLeave = null;
foreach($res['body'] as $l) {
    if ($l['reason'] == "Test Leave Full Flow") {
        $targetLeave = $l;
        break;
    }
}

if ($targetLeave) {
    echo "[PASS] HoD sees leave after substitute acceptance\n";
    $res = req("/leaves.php/" . $targetLeave['id'] . "/approve/hod", "PUT", ["status" => "Approved"], $hodToken);
    assertEq($res['code'], 200, "HoD Approve");
} else {
    echo "[FAIL] HoD does not see leave\n";
    print_r($res);
    exit(1);
}


echo "\n--- 6. Principal Verification ---\n";
$res = req("/leaves.php/pending/principal", "GET", null, $princToken);
$targetLeaveP = null;
foreach($res['body'] as $l) {
    if ($l['reason'] == "Test Leave Full Flow") {
        $targetLeaveP = $l;
        break;
    }
}
if ($targetLeaveP) {
    echo "[PASS] Principal sees leave\n";
    $res = req("/leaves.php/" . $targetLeaveP['id'] . "/approve/principal", "PUT", ["status" => "Approved"], $princToken);
    assertEq($res['code'], 200, "Principal Approve");
} else {
    echo "[FAIL] Principal does not see leave\n";
    print_r($res);
    exit(1);
}

echo "\n=== ALL TESTS PASSED ===\n";
?>
