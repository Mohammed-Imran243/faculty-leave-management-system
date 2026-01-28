-- Add columns for Hourly Leave
ALTER TABLE leave_requests 
ADD COLUMN duration_type ENUM('Days', 'Hours') DEFAULT 'Days',
ADD COLUMN selected_hours VARCHAR(50) DEFAULT NULL; -- Stores "1,2,3" etc.
