-- Database Schema V2: Advanced Faculty Leave System

-- 1. Users Table (Enhanced)
ALTER TABLE users ADD COLUMN IF NOT EXISTS signature_path VARCHAR(255) DEFAULT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS is_active BOOLEAN DEFAULT TRUE;

-- 2. Leave Requests Table (Re-structuring)
-- We'll modify the existing table to match new requirements
ALTER TABLE leave_requests ADD COLUMN IF NOT EXISTS total_days DECIMAL(5,2) DEFAULT 0;
ALTER TABLE leave_requests ADD COLUMN IF NOT EXISTS current_step_user_id INT DEFAULT NULL; -- For complex workflows
ALTER TABLE leave_requests ADD COLUMN IF NOT EXISTS pdf_path VARCHAR(255) DEFAULT NULL;
ALTER TABLE leave_requests ADD COLUMN IF NOT EXISTS rejection_reason TEXT DEFAULT NULL;

-- 3. Leave Substitutions Table (New)
CREATE TABLE IF NOT EXISTS leave_substitutions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    leave_request_id INT NOT NULL,
    date DATE NOT NULL,
    hour_slot INT NOT NULL CHECK (hour_slot BETWEEN 0 AND 8),
    substitute_user_id INT NOT NULL,
    status ENUM('PENDING', 'ACCEPTED', 'REJECTED') DEFAULT 'PENDING',
    rejection_reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (leave_request_id) REFERENCES leave_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (substitute_user_id) REFERENCES users(id)
);

-- 4. Approvals Table (New - For Digital Signatures)
CREATE TABLE IF NOT EXISTS approvals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    leave_request_id INT NOT NULL,
    approver_id INT NOT NULL,
    role_at_time VARCHAR(50) NOT NULL,
    action ENUM('APPROVED', 'REJECTED') NOT NULL,
    signature_snapshot VARCHAR(255), -- Path to signature used
    comments TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (leave_request_id) REFERENCES leave_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (approver_id) REFERENCES users(id)
);

-- 5. Audit Logs Table (New)
CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entity_type VARCHAR(50) NOT NULL, -- LEAVE, USER
    entity_id INT NOT NULL,
    action VARCHAR(50) NOT NULL,
    actor_id INT, -- Nullable for System actions
    details JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- seed_data.sql
-- Default Principal and HoD if not exists
INSERT INTO users (name, email, password_hash, role, department) 
VALUES 
('Principal User', 'principal@college.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'principal', 'Administration'),
('HoD Computer Science', 'hod.cs@college.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'hod', 'Computer Science')
ON DUPLICATE KEY UPDATE role=VALUES(role);

-- 6. Notifications Table (New)
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
