<?php
require_once 'includes/config.php';
require_once 'includes/helpers.php';

echo "Testing Role Helper Functions\n";
echo "---------------------------\n";

$roles = ['faculty', 'Assistant Professor (AP)', 'Associate Professor', 'Professor', 'hod', 'principal', 'admin', 'officer'];

foreach ($roles as $role) {
    echo "Role: " . str_pad($role, 25) . " | isTeachingRole: " . (isTeachingRole($role) ? 'YES' : 'NO') . " | isAdministrativeRole: " . (isAdministrativeRole($role) ? 'YES' : 'NO') . "\n";
}

echo "\nVerification complete.\n";
