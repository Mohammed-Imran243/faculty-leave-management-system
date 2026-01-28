<?php
// Standalone DB Connection for CLI
$host = 'localhost';
$db_name = 'faculty_system';
$username = 'root';
$password = 'Imran@123'; 

try {
    $conn = new PDO("mysql:host=$host;dbname=$db_name", $username, $password);
    
    echo "--- Table: leave_substitutions ---\n";
    $stmt = $conn->query("DESCRIBE leave_substitutions");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($columns, JSON_PRETTY_PRINT);

} catch(PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
