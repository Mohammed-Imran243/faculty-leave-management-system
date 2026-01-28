<?php
// helpers
function req($url, $method, $data = null, $token = null) {
    $opts = [
        'http' => [
            'method' => $method,
            'header' => "Content-type: application/json\r\n" . 
                        ($token ? "Authorization: Bearer $token\r\n" : ""),
            'content' => $data ? json_encode($data) : null,
            'ignore_errors' => true
        ]
    ];
    $context = stream_context_create($opts);
    $res = file_get_contents($url, false, $context);
    return json_decode($res, true);
}

echo "--- 1. Login as Hari ---\n";
$login = req('http://localhost/faculty-system/server/api/auth.php/login', 'POST', ['username'=>'hari', 'password'=>'123456']);

if (isset($login['error'])) {
    die("Login Failed: " . print_r($login, true));
}
$token = $login['token'];
echo "Token Acquired.\n";

echo "\n--- 2. Get Pending Substitutions ---\n";
$pending = req('http://localhost/faculty-system/server/api/leaves.php/substitutions/pending', 'GET', null, $token);
print_r($pending);

if (empty($pending)) {
    die("No pending substitutions found to test.\n");
}

$sub_id = $pending[0]['id'];
echo "\n--- 3. Attempt to ACCEPT Substitution ID: $sub_id ---\n";
$url = "http://localhost/faculty-system/server/api/leaves.php/substitutions/$sub_id/respond";
echo "Target URL: $url\n";

$res = req($url, 'PUT', ['status' => 'ACCEPTED'], $token);

echo "\n--- Response ---\n";
print_r($res);
?>
