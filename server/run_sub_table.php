<?php
$host = 'localhost';
$db_name = 'faculty_system';
$username = 'root';
$password = 'Imran@123'; 

try {
    $conn = new PDO("mysql:host=$host;dbname=$db_name", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $sql = file_get_contents('create_substitutions_table.sql');
    $conn->exec($sql);
    echo "Table leave_substitutions created successfully.\n";

} catch(PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
