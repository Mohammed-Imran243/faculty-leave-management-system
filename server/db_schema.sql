-- Database: faculty_system

CREATE DATABASE IF NOT EXISTS faculty_system;
USE faculty_system;

-- Users Table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'faculty', 'hod', 'principal') NOT NULL DEFAULT 'faculty',
    department VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Leave Requests Table
CREATE TABLE IF NOT EXISTS leave_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    leave_type VARCHAR(50) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    reason TEXT,
    
    -- Approval Workflow Columns
    hod_status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
    principal_status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
    
    -- Hourly Leave Support
    duration_type ENUM('Days', 'Hours') DEFAULT 'Days',
    selected_hours VARCHAR(255), -- Comma separated 1,2,3 etc
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Default Admin (Password: admin123)
-- Hash: $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi
INSERT INTO users (name, email, password_hash, role, department) 
VALUES ('System Admin', 'admin@college.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'Administration')
ON DUPLICATE KEY UPDATE email=email;
