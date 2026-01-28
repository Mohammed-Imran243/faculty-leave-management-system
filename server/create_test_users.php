<?php
// Create Test Users Script
$baseUrl = "http://localhost/faculty-system/server/api";

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
    
    return ['code' => $code, 'body' => json_decode($response, true)];
}

// 1. Login as Admin
echo "Logging in as Admin...\n";
$res = req("/auth.php/login", "POST", ["email" => "admin@college.edu", "password" => "admin123"]);
if ($res['code'] !== 200) {
    die("Admin Login Failed. Ensure Admin exists.\n");
}
$adminToken = $res['body']['token'];
echo "Admin Logged In.\n";

// 2. Create Faculty 1
$fac1 = [
    "name" => "Faculty One",
    "email" => "faculty1@test.com",
    "password" => "admin123",
    "role" => "faculty",
    "department" => "CSE"
];
$res = req("/users.php/create", "POST", $fac1, $adminToken);
echo "Create Faculty 1: " . ($res['code'] == 200 ? "Success" : "Failed") . "\n";

// 3. Create Faculty 2
$fac2 = [
    "name" => "Faculty Two",
    "email" => "faculty2@test.com",
    "password" => "admin123",
    "role" => "faculty",
    "department" => "CSE"
];
$res = req("/users.php/create", "POST", $fac2, $adminToken);
echo "Create Faculty 2: " . ($res['code'] == 200 ? "Success" : "Failed") . "\n";

echo "Done.\n";
?>
