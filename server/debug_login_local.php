<?php
$url = 'http://localhost/faculty-system/server/api/auth.php/login';
$data = ['username' => 'hari', 'password' => '123456'];

$options = [
    'http' => [
        'header'  => "Content-type: application/json\r\n",
        'method'  => 'POST',
        'content' => json_encode($data),
        'ignore_errors' => true 
    ]
];

$context  = stream_context_create($options);
$result = file_get_contents($url, false, $context);

echo "--- RAW RESPONSE START ---\n";
var_dump($result);
echo "--- RAW RESPONSE END ---\n";

echo "HTTP Headers:\n";
print_r($http_response_header);
?>
