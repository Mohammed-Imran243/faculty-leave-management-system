<?php
// Test Script for Faculty System V4 (Username Login)
$baseUrl = "http://127.0.0.1/faculty-system/server/api";
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

echo "--- 1. Login with Default Admin (Username: admin) ---\n";
// The DB update script should have set 'admin' as username for 'admin@college.edu'
$res = req("/auth.php/login", "POST", ["username" => "admin", "password" => "admin123"]);
assertEq($res['code'], 200, "Admin Login via Username");
$adminToken = $res['body']['token'];
echo "Token received.\n";

echo "\n--- 2. Create New User with Username ---\n";
$ts = time();
$newUser = [
    "name" => "TestUser_$ts",
    "username" => "user_$ts",
    "email" => "user_$ts@test.com",
    "password" => "pass123",
    "role" => "faculty",
    "department" => "CSE"
];
$res = req("/users.php/create", "POST", $newUser, $adminToken);
assertEq($res['code'], 200, "Create User with Username");

echo "\n--- 3. Login with New User ---\n";
$res = req("/auth.php/login", "POST", ["username" => "user_$ts", "password" => "pass123"]);
assertEq($res['code'], 200, "New User Login");
if (isset($res['body']['user']['username']) && $res['body']['user']['username'] === "user_$ts") {
    echo "[PASS] Username returned in login response\n";
} else {
    echo "[FAIL] Username missing or incorrect in login response\n";
    print_r($res['body']);
}

echo "\n--- 4. Fail Login with Email (Should fail now) ---\n";
// Passing email in username field should fail if backend checks effectively, 
// or if we rely on backend logic looking for 'username' key strictly? 
// The backend now looks for $data->username. If we pass email as 'username' value, it will fail lookup.
$res = req("/auth.php/login", "POST", ["username" => "user_$ts@test.com", "password" => "pass123"]);
// Should fail because we stored 'user_$ts' as username, not the email
assertEq($res['code'], 401, "Login with Email as Username (Fail Expected)");

echo "\n=== ALL TESTS PASSED ===\n";
?>
