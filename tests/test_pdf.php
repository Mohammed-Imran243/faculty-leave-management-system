<?php
// ==========================================
// TEST SUITE: EFFICIENCY & PDF TEST
// ==========================================

$base_url = "http://localhost/faculty-leave-management-system%20cr";

require_once '../includes/config.php';
require_once '../includes/SimpleJWT.php';

echo "<h2>Executing PDF Render Benchmarks...</h2>";

// 1. Fetch valid approved target
$stmt = $conn->prepare("SELECT id, user_id FROM permissions WHERE status = 'Approved' LIMIT 1");
$stmt->execute();
$target = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$target) {
    die("No 'Approved' permissions found in the database. Please approve one first before running PDF checks.");
}

// 2. Fetch User to derive Token
$user_stmt = $conn->prepare("SELECT id, role, department, name FROM users WHERE id = :uid");
$user_stmt->execute([':uid' => $target['user_id']]);
$test_user = $user_stmt->fetch(PDO::FETCH_ASSOC);

$payload = [
    'id' => $test_user['id'],
    'role' => $test_user['role'],
    'name' => $test_user['name'],
    'department' => $test_user['department'],
    'iat' => time(),
    'exp' => time() + (60 * 60 * 24)
];
$token = JWT::encode($payload);


$iterations = 50;
echo "Pinging <b>generate_permission_pdf.php?id=" . $target['id'] . "</b> sequentially $iterations times...<br><br>";

$times = [];
$sizes = [];
$success_count = 0;

for ($i = 0; $i < $iterations; $i++) {
    $start = microtime(true);
    
    $options = [
        'http' => [
            'method' => 'GET',
            'header' => "Authorization: Bearer " . $token . "\r\n",
            'ignore_errors' => true
        ]
    ];
    $context = stream_context_create($options);
    
    $result = file_get_contents($base_url . "/server/api/generate_permission_pdf.php?id=" . $target['id'], false, $context);
    
    $end = microtime(true);
    $exec_time = ($end - $start) * 1000; // in Ms
    
    if (strpos($http_response_header[0], '200') !== false && strlen($result) > 500) {
        $success_count++;
        $times[] = $exec_time;
        $sizes[] = strlen($result);
    }
}

$avg_time = count($times) > 0 ? array_sum($times) / count($times) : 0;
$avg_size = count($sizes) > 0 ? (array_sum($sizes) / count($sizes)) / 1024 : 0;

echo "<h3>Results</h3>";
echo "Successful Renderings: <b>$success_count / $iterations</b><br>";
echo "Average Render Time: <b>" . round($avg_time, 2) . " ms</b><br>";
echo "Average Output Size: <b>" . round($avg_size, 2) . " KB</b><br>";

if ($success_count == $iterations && $avg_time < 2000) {
    echo "<span style='color: green; font-weight:bold;'>✔ PASS: PDF Library is stable and efficient.</span>";
} else {
    echo "<span style='color: red; font-weight:bold;'>✘ FAIL: PDF Library performance or stability degradation detected.</span>";
}

?>
