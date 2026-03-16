<?php
// ==========================================
// TEST SUITE: LOGIC & LIMITS
// ==========================================

$base_url = "http://localhost/faculty-leave-management-system%20cr"; // Update this if your XAMPP runs on a different port

require_once '../includes/config.php';
require_once '../includes/SimpleJWT.php';

echo "<h2>Executing Automated Logic Tests...</h2>";

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

function simulate_post($endpoint, $data, $token, $base_url) {
    $url = $base_url . "/server/api/" . $endpoint;
    $options = [
        'http' => [
            'header'  => "Content-Type: application/json\r\n" .
                         "Authorization: Bearer " . $token . "\r\n",
            'method'  => 'POST',
            'content' => json_encode($data),
            'ignore_errors' => true // Allows us to catch 400/500 responses instead of failing
        ]
    ];
    $context  = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    return json_decode($result, true);
}

function assert_test($test_name, $expected_pass, $response) {
    echo "<b>Test: $test_name</b><br>";
    $is_success = !isset($response['error']);
    
    if ($is_success === $expected_pass) {
        echo "<span style='color: green;'>✔ PASS</span><br>";
    } else {
        echo "<span style='color: red;'>✘ FAIL</span><br>";
        echo "Response: " . json_encode($response) . "<br>";
    }
    echo "<hr>";
}

// ---------------------------------------------------------
// PREP: Clean up today's permissions to ensure fresh start
// ---------------------------------------------------------
$clean_stmt = $conn->prepare("DELETE FROM permissions WHERE user_id = :uid AND DATE(permission_date) = CURDATE()");
$clean_stmt->execute([':uid' => $test_user['id']]);

$clean_stmt2 = $conn->prepare("DELETE FROM leaves WHERE user_id = :uid");
$clean_stmt2->execute([':uid' => $test_user['id']]);


// ---------------------------------------------------------
// TEST 1: Valid time (09:30-10:30) -> EXPECT PASS
// ---------------------------------------------------------
$data1 = [
    "permission_date" => date('Y-m-d'),
    "start_time" => "09:30",
    "end_time" => "10:30",
    "reason" => "Automated Test 1"
];
$res1 = simulate_post("permissions.php", $data1, $token, $base_url);
assert_test("Apply for 1 Permission at a valid time (09:30-10:30)", true, $res1);


// ---------------------------------------------------------
// TEST 2: Second Permission in the same month -> EXPECT FAIL
// ---------------------------------------------------------
$data2 = [
    "permission_date" => date('Y-m-d', strtotime('+1 day')),
    "start_time" => "09:30",
    "end_time" => "10:30",
    "reason" => "Automated Test 2 limit breach"
];
$res2 = simulate_post("permissions.php", $data2, $token, $base_url);
assert_test("Apply for a 2nd Permission in the same month limit trigger", false, $res2);


// ---------------------------------------------------------
// TEST 3: Invalid time (11:00-12:00) -> EXPECT FAIL
// ---------------------------------------------------------
// First cleanup the first one so we don't trigger limit fail instead of time fail
$clean_stmt->execute([':uid' => $test_user['id']]);

$data3 = [
    "permission_date" => date('Y-m-d'),
    "start_time" => "11:00",
    "end_time" => "12:00",
    "reason" => "Automated Test 3 invalid time"
];
$res3 = simulate_post("permissions.php", $data3, $token, $base_url);
assert_test("Apply for a Permission at an invalid core hours time (11:00-12:00)", false, $res3);


// ---------------------------------------------------------
// TEST 4: Apply for 13 Casual Leaves -> EXPECT FAIL
// ---------------------------------------------------------
$data4 = [
    "leave_type" => "Casual Leave",
    "start_date" => date('Y-m-d'),
    "end_date" => date('Y-m-d', strtotime('+12 days')), // 13 days
    "reason" => "Automated Test 4 Limit",
    "is_override" => false
];
$res4 = simulate_post("leaves.php", $data4, $token, $base_url);
assert_test("Apply for 13 Casual Leaves without override", false, $res4);


// ---------------------------------------------------------
// TEST 5: Apply for 13 Casual Leaves with Override -> EXPECT PASS
// ---------------------------------------------------------
$data5 = [
    "leave_type" => "Casual Leave",
    "start_date" => date('Y-m-d'),
    "end_date" => date('Y-m-d', strtotime('+12 days')), // 13 days
    "reason" => "Automated Test 5 Override",
    "is_override" => true
];
$res5 = simulate_post("leaves.php", $data5, $token, $base_url);
assert_test("Apply for 13 Casual Leaves using is_override (Admin/Principal override)", true, $res5);

echo "<h3>Tests Complete.</h3>";
?>
