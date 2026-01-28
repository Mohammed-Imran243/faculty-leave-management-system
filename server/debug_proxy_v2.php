<?php
$url = 'http://localhost/faculty-system/server/api/auth.php/login';
$data = array('username' => 'hari', 'password' => '123456');

$options = array(
    'http' => array(
        'header'  => "Content-type: application/json\r\n",
        'method'  => 'POST',
        'content' => json_encode($data),
        'ignore_errors' => true
    )
);
$context  = stream_context_create($options);
$result = file_get_contents($url, false, $context);

echo "|||START|||";
var_dump($result);
echo "|||END|||";
?>
