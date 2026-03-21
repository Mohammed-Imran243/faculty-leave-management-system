<?php
/**
 * Final Bulk Faculty Import Script
 * Imports faculty data from scripts/faculty_data.csv into the users table.
 */

// Disable web access for safety
if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.\n");
}

require_once __DIR__ . '/core/bootstrap.php';

$csvFile = __DIR__ . '/../scripts/faculty_data.csv';

if (!file_exists($csvFile)) {
    die("Error: faculty_data.csv not found at $csvFile\n");
}

$handle = fopen($csvFile, 'r');
$header = fgetcsv($handle); // Skip header

$stats = [
    'processed' => 0,
    'inserted' => 0,
    'updated' => 0,
    'errors' => 0
];

// Mapping for roles based on role_id column (Index 9)
$roleMapping = [
    1 => 'admin',     // System Admin / Developer
    2 => 'hod',       // Head of Department
    3 => 'admin',     // Officer / Staff (Administrative)
    4 => 'faculty'    // Teaching Staff
];

// Mapping for departments based on department_id column (Index 8)
// Based on analysis: 1 -> Computer Science, 4 -> Administration
$deptMapping = [
    1 => 'Computer Science',
    4 => 'Administration'
];

echo "Starting import...\n";

$conn->beginTransaction();

try {
    $stmt = $conn->prepare("
        INSERT INTO users (name, username, employee_code, designation, email, password_hash, role, department) 
        VALUES (:name, :username, :emp_code, :designation, :email, :password_hash, :role, :department)
        ON DUPLICATE KEY UPDATE 
            name = VALUES(name),
            designation = VALUES(designation),
            email = VALUES(email),
            role = VALUES(role),
            department = VALUES(department)
    ");

    while (($data = fgetcsv($handle)) !== FALSE) {
        if (count($data) < 10) continue; // Skip malformed rows

        $stats['processed']++;
        
        $emp_code = trim($data[1]);
        $name = trim($data[2]);
        $email = !empty($data[3]) ? trim($data[3]) : null;
        $designation = !empty($data[5]) ? trim($data[5]) : null;
        $dept_id = (int)$data[8];
        $role_id = (int)$data[9];
        
        // Map Role
        $role = isset($roleMapping[$role_id]) ? $roleMapping[$role_id] : 'faculty';
        
        // Map Department
        $department = isset($deptMapping[$dept_id]) ? $deptMapping[$dept_id] : null;
        
        // Use Bcrypt hash of emp_code as initial password
        $password_hash = password_hash($emp_code, PASSWORD_BCRYPT);
        
        $params = [
            ':name'          => $name,
            ':username'      => $emp_code, 
            ':emp_code'      => $emp_code,
            ':designation'   => $designation,
            ':email'         => $email,
            ':password_hash' => $password_hash,
            ':role'          => $role,
            ':department'    => $department
        ];

        if ($stmt->execute($params)) {
            if ($stmt->rowCount() == 1) {
                $stats['inserted']++;
            } else {
                $stats['updated']++;
            }
        } else {
            $stats['errors']++;
            echo "Error processing $emp_code ($name)\n";
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

echo "\n--- Import Summary ---\n";
echo "Total Records Processed: " . $stats['processed'] . "\n";
echo "New Records Inserted  : " . $stats['inserted'] . "\n";
echo "Existing Updated      : " . $stats['updated'] . "\n";
echo "Errors Encountered    : " . $stats['errors'] . "\n";
echo "-----------------------\n";
