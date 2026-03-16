-- db_extra_modules.sql
-- Extra independent modules for Faculty System: Permission, Outpass, Notifications

-- 1. Notifications Table
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    type VARCHAR(50) DEFAULT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_notif_user (user_id),
    INDEX idx_notif_unread (is_read)
);

-- 2. Faculty Permissions Table
CREATE TABLE IF NOT EXISTS faculty_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    permission_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    reason TEXT,
    status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
    is_override BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_perm_user (user_id),
    INDEX idx_perm_date (permission_date),
    INDEX idx_perm_status (status)
);

-- 3. Faculty Outpasses Table
CREATE TABLE IF NOT EXISTS faculty_outpasses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    outpass_date DATE NOT NULL,
    out_time TIME NOT NULL,
    in_time TIME NULL DEFAULT NULL,
    reason TEXT,
    status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
    is_override BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_outpass_user (user_id),
    INDEX idx_outpass_date (outpass_date),
    INDEX idx_outpass_status (status)
);
