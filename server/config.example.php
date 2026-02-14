<?php
/**
 * Copy this file to config.local.php and set your database credentials and JWT secret.
 * config.local.php should not be committed to version control.
 */
$host     = '127.0.0.1';
$db_name  = 'faculty_system';
$username = 'root';
$password = 'your_password_here';

// Optional: set JWT secret for token signing (or use env JWT_SECRET)
if (!defined('JWT_SECRET')) {
    define('JWT_SECRET', 'your-random-secret-key-change-in-production');
}
