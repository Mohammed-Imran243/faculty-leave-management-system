<?php
/**
 * Bulk Faculty Import Script
 * Securely imports faculty data from faculty_data.csv
 */

// Disable web access for safety
if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.");
}

require_once __DIR__ . '/../includes/config.php';

$csvFile = __DIR__ . '/../faculty_data.csv';


if (!file_exists($csvFile)) {
    die("Error: faculty_data.csv not found.\n");
}

$handle = fopen($csvFile, 'r');
$header = fgetcsv($handle); // Skip header

$stats = [
    'processed' => 0,
    'inserted' => 0,
    'skipped' => 0,
    'errors' => 0
];

$conn->beginTransaction();

try {
    // Prepare statement with ON DUPLICATE KEY UPDATE to prevent overwriting
    // We assume employee_code or username has a UNIQUE index
    $stmt = $conn->prepare("
        INSERT INTO users (name, username, employee_code, designation, email, password_hash, role, department) 
        VALUES (:name, :username, :emp_code, :designation, :email, :password_hash, :role, NULL)
        ON DUPLICATE KEY UPDATE username = username
    ");

    while (($data = fgetcsv($handle)) !== FALSE) {
        $stats['processed']++;
        
        // CSV Mapping (based on observed structure)
        // 0: faculty_id, 1: emp_code, 2: name,  designaton is at 5
        // 1: emp_code
        // 2: name
        // 3: email
        // 5: designation
        // 9: role_id
        
        $emp_code = $data[1];
        $name = $data[2];
        $email = !empty($data[3]) ? $data[3] : null;
        $designation = !empty($data[5]) ? $data[5] : null;
        $role_id = $data[9];
        
        // Mapping role_id: 1 -> admin, 4 -> faculty
        $role = 'faculty';
        if ($role_id == 1) $role = 'admin';
        
        // Generate Bcrypt hash of emp_code for initial password
        $password_hash = password_hash($emp_code, PASSWORD_BCRYPT);
        
        $params = [
            ':name' => $name,
            ':username' => $emp_code, // Mapping emp_code to username as requested
            ':emp_code' => $emp_code,
            ':designation' => $designation,
            ':email' => $email,
            ':password_hash' => $password_hash,
            ':role' => $role
        ];

        if ($stmt->execute($params)) {
            if ($stmt->rowCount() > 0) {
                $stats['inserted']++;
            } else {
                $stats['skipped']++;
            }
        } else {
            $stats['errors']++;
        }
    }

    $conn->commit();
    echo "Import completed successfully.\n";
} catch (Exception $e) {
    $conn->rollBack();
    echo "Import failed: " . $e->getMessage() . "\n";
    exit(1);
}

fclose($handle);

echo "--- Summary ---\n";
echo "Total Records Processed: " . $stats['processed'] . "\n";
echo "Successfully Inserted: " . $stats['inserted'] . "\n";
echo "Skipped (Duplicates): " . $stats['skipped'] . "\n";
echo "Errors: " . $stats['errors'] . "\n";
