-- Update ENUM for faculty_permissions
ALTER TABLE faculty_permissions 
MODIFY COLUMN status ENUM('Pending', 'Pending_HOD', 'Pending_Principal', 'Approved', 'Rejected') DEFAULT 'Pending_HOD';

-- Update ENUM for faculty_outpasses
ALTER TABLE faculty_outpasses 
MODIFY COLUMN status ENUM('Pending', 'Pending_HOD', 'Pending_Principal', 'Approved', 'Rejected') DEFAULT 'Pending_HOD';

-- Update existing 'Pending' to 'Pending_HOD' to align with the new workflow
UPDATE faculty_permissions SET status = 'Pending_HOD' WHERE status = 'Pending';
UPDATE faculty_outpasses SET status = 'Pending_HOD' WHERE status = 'Pending';
