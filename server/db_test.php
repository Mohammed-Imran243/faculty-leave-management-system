<?php
// db_test.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>Database Connection Test</h1>";

require_once 'config.php';

try {
    echo "<p>✅ Config file loaded.</p>";
    echo "<p>Attempting to connect to <strong>$db_name</strong> at <strong>$host</strong> with user <strong>$username</strong>...</p>";

    if ($conn) {
        echo "<h2 style='color:green'>SUCCESS: Connected to Database!</h2>";
        
        // Test Query
        $stmt = $conn->query("SELECT count(*) as count FROM users");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<p>Found <strong>" . $row['count'] . "</strong> users in the database.</p>";
        
        // Check Admin
        $stmt = $conn->query("SELECT * FROM users WHERE email='admin@college.edu'");
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($admin) {
            echo "<p style='color:green'>✅ Admin user found: " . $admin['email'] . "</p>";
            echo "<p>Role: " . $admin['role'] . "</p>";
        } else {
            echo "<p style='color:red'>❌ Admin user NOT found. Please import the db_schema.sql file.</p>";
        }

    }
} catch (PDOException $e) {
    echo "<h2 style='color:red'>CONNECTION FAILED</h2>";
    echo "<p>Error Message: " . $e->getMessage() . "</p>";
    echo "<h3>Troubleshooting:</h3>";
    echo "<ul>";
    echo "<li>Check if XAMPP MySQL is running.</li>";
    echo "<li>Check your password in <code>server/config.php</code>.</li>";
    echo "<li>The error 'Access denied' means wrong username/password.</li>";
    echo "<li>The error 'Unknown database' means you didn't create the database in phpMyAdmin.</li>";
    echo "</ul>";
}
?>
