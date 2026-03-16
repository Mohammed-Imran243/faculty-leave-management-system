<?php
// ==========================================
// TEST SUITE: RACE CONDITION STRESS TEST
// ==========================================

$base_url = "http://localhost/faculty-leave-management-system%20cr"; 

require_once '../includes/config.php';
require_once '../includes/SimpleJWT.php';

echo "<h2>Executing Concurrency Stress Test...</h2>";

// 1. Create/Find a test user and generate a JWT token
$stmt = $conn->prepare("SELECT id, role, department, name FROM users WHERE role = 'faculty' LIMIT 1");
$stmt->execute();
$test_user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$test_user) {
    die("No faculty user found in database to execute tests.");
}

$payload = [
    'id' => $test_user['id'],
    'role' => $test_user['role'],
    'name' => $test_user['name'],
    'department' => $test_user['department'],
    'iat' => time(),
    'exp' => time() + (60 * 60 * 24)
];
$token = JWT::encode($payload);

// ---------------------------------------------------------
// PREP: Clean up today's permissions to ensure fresh start
// ---------------------------------------------------------
$clean_stmt = $conn->prepare("DELETE FROM permissions WHERE user_id = :uid AND DATE(permission_date) = CURDATE()");
$clean_stmt->execute([':uid' => $test_user['id']]);


$concurrency_levels = 100;
$multi_curl = curl_multi_init();
$handles = [];

echo "Initializing $concurrency_levels concurrent POST requests to <b>/api/permissions.php</b>...<br>";

// Prepare data
$data = json_encode([
    "permission_date" => date('Y-m-d'),
    "start_time" => "09:30",
    "end_time" => "10:30",
    "reason" => "Mass Concurrency Test"
]);

for ($i = 0; $i < $concurrency_levels; $i++) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $base_url . "/server/api/permissions.php");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token
    ]);
    
    curl_multi_add_handle($multi_curl, $ch);
    $handles[] = $ch;
}

echo "Firing requests simultaneously...<br>";

// Execute all queries simultaneously
$running = null;
do {
    curl_multi_exec($multi_curl, $running);
    curl_multi_select($multi_curl);
} while ($running > 0);

// Close handles
foreach ($handles as $ch) {
    curl_multi_remove_handle($multi_curl, $ch);
    curl_close($ch);
}
curl_multi_close($multi_curl);

echo "All requests finished.<br>";

// ---------------------------------------------------------
// RESULT: Check database for duplicate insertions
// ---------------------------------------------------------
$check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM permissions WHERE user_id = :uid AND DATE(permission_date) = CURDATE() AND reason = 'Mass Concurrency Test'");
$check_stmt->execute([':uid' => $test_user['id']]);
$res = $check_stmt->fetch(PDO::FETCH_ASSOC);

echo "<h3>Stress Test Results</h3>";
echo "Total Valid Records Inserted: <b>" . $res['count'] . "</b><br>";

if ($res['count'] == 1) {
    echo "<span style='color: green; font-weight:bold;'>✔ PASS: Database strictly enforced the 1-per-month limit without race conditions!</span>";
} else {
    echo "<span style='color: red; font-weight:bold;'>✘ FAIL: Race condition vulnerability detected! Multiple rows bypassed the application logic check! </span>";
}

?>
