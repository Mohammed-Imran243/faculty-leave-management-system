<?php
require_once '../../includes/config.php';

try {
    // 1. Update Permissions ENUM
    $stmt1 = $conn->prepare("ALTER TABLE permissions MODIFY COLUMN status ENUM('Pending', 'Pending_HOD', 'Pending_Principal', 'Approved', 'Rejected') DEFAULT 'Pending_HOD'");
    $stmt1->execute();
    echo "Permissions table status ENUM updated successfully.\n";

    // 2. Update Outpasses ENUM
    $stmt2 = $conn->prepare("ALTER TABLE outpasses MODIFY COLUMN status ENUM('Pending', 'Pending_HOD', 'Pending_Principal', 'Approved', 'Rejected') DEFAULT 'Pending_HOD'");
    $stmt2->execute();
    echo "Outpasses table status ENUM updated successfully.\n";

    // 3. Migrate existing 'Pending' statuses to 'Pending_HOD' to match new flow (optional safety)
    $stmt3 = $conn->prepare("UPDATE permissions SET status = 'Pending_HOD' WHERE status = 'Pending'");
    $stmt3->execute();
    echo "Migrated legacy 'Pending' permissions to 'Pending_HOD'.\n";

    $stmt4 = $conn->prepare("UPDATE outpasses SET status = 'Pending_HOD' WHERE status = 'Pending'");
    $stmt4->execute();
    echo "Migrated legacy 'Pending' outpasses to 'Pending_HOD'.\n";
    
    // Now we can strictly restrict the ENUM to remove the legacy 'Pending' if we want, but keeping it for safety during transition is fine.
    $stmt5 = $conn->prepare("ALTER TABLE permissions MODIFY COLUMN status ENUM('Pending_HOD', 'Pending_Principal', 'Approved', 'Rejected') DEFAULT 'Pending_HOD'");
    $stmt5->execute();
    $stmt6 = $conn->prepare("ALTER TABLE outpasses MODIFY COLUMN status ENUM('Pending_HOD', 'Pending_Principal', 'Approved', 'Rejected') DEFAULT 'Pending_HOD'");
    $stmt6->execute();
    echo "Finalized strict ENUM constraints.\n";

    echo "Migration Complete.";
} catch (PDOException $e) {
    echo "Migration Failed: " . $e->getMessage();
}
?>
