<?php
namespace App\Repositories;

use App\Core\Database;

class LeaveRepository {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function getUsedDays($userId, $leaveType, $month = null, $year = null) {
        if (!$year) $year = date('Y');
        $sql = "SELECT start_date, end_date FROM leave_requests 
                WHERE user_id = :uid 
                AND leave_type = :type 
                AND hod_status NOT IN ('Rejected', 'Cancelled') 
                AND principal_status NOT IN ('Rejected', 'Cancelled')";
        
        $leaves = $this->db->query($sql, [
            ':uid'  => $userId,
            ':type' => $leaveType
        ])->fetchAll();

        $total = 0;
        foreach ($leaves as $l) {
            $start = new \DateTime($l['start_date']);
            $end = new \DateTime($l['end_date']);
            $curr = clone $start;
            while ($curr <= $end) {
                $matchesMonth = $month ? ($curr->format('m') == $month) : true;
                if ($matchesMonth && $curr->format('Y') == $year && $curr->format('N') != 7) {
                    $total++;
                }
                $curr->modify('+1 day');
            }
        }
        return $total;
    }

    public function hasOverlap($userId, $start, $end) {
        $sql = "SELECT COUNT(*) FROM leave_requests 
                WHERE user_id = :uid 
                AND hod_status != 'Rejected' 
                AND principal_status != 'Rejected'
                AND (
                    (start_date BETWEEN :s AND :e) OR 
                    (end_date BETWEEN :s AND :e) OR 
                    (:s BETWEEN start_date AND end_date)
                )";
        return $this->db->query($sql, [
            ':uid' => $userId, ':s' => $start, ':e' => $end
        ])->fetchColumn() > 0;
    }

    public function logApproval($leaveId, $userId, $role, $status) {
        $action = strtoupper($status);
        $sql = "INSERT INTO approvals (leave_request_id, approver_id, role_at_time, action) 
                VALUES (:lid, :aid, :role, :action)";
        $this->db->query($sql, [
            ':lid'    => $leaveId,
            ':aid'    => $userId,
            ':role'   => $role,
            ':action' => $action
        ]);
    }

    public function releaseSubstitutions($leaveId) {
        $sql = "UPDATE leave_substitutions SET status = 'CANCELLED' WHERE leave_request_id = :lid";
        $this->db->query($sql, [':lid' => $leaveId]);
    }

    public function checkDuplicate($userId, $start, $end, $type) {
        $sql = "SELECT COUNT(*) FROM leave_requests 
                WHERE user_id = :uid AND start_date = :s AND end_date = :e 
                AND leave_type = :t AND created_at > (NOW() - INTERVAL 1 MINUTE)";
        return $this->db->query($sql, [
            ':uid' => $userId, ':s' => $start, ':e' => $end, ':t' => $type
        ])->fetchColumn() > 0;
    }

    public function create($data) {
        $sql = "INSERT INTO leave_requests (user_id, leave_type, start_date, end_date, reason, duration_type, selected_hours, hod_status, principal_status, is_override) 
                VALUES (:uid, :type, :start, :end, :reason, :d_type, :hours, :h_status, :p_status, :override)";
        
        $this->db->query($sql, [
            ':uid'      => $data['user_id'],
            ':type'     => $data['leave_type'],
            ':start'    => $data['start_date'],
            ':end'      => $data['end_date'],
            ':reason'   => $data['reason'],
            ':d_type'   => $data['duration_type'] ?? 'Days',
            ':hours'    => $data['selected_hours'] ?? null,
            ':h_status' => $data['hod_status'] ?? 'Pending',
            ':p_status' => $data['principal_status'] ?? 'Pending',
            ':override' => $data['is_override'] ?? 0
        ]);
        
        return $this->db->getConnection()->lastInsertId();
    }

    public function addSubstitution($leaveId, $date, $period, $subId, $className = null, $subjectCode = null) {
        $sql = "INSERT INTO leave_substitutions (leave_request_id, date, hour_slot, class_name, subject_code, substitute_user_id, status) 
                VALUES (:lid, :date, :slot, :cname, :subcode, :sid, 'PENDING')";
        $this->db->query($sql, [
            ':lid'   => $leaveId,
            ':date'  => $date,
            ':slot'  => $period,
            ':cname' => $className,
            ':subcode' => $subjectCode,
            ':sid'   => $subId
        ]);
    }


    public function checkSubstituteConflict($subId, $date, $slot) {
        $sql = "SELECT COUNT(*) FROM leave_substitutions 
                WHERE substitute_user_id = :sid 
                AND date = :date 
                AND hour_slot = :slot 
                AND status = 'ACCEPTED'";
        return $this->db->query($sql, [
            ':sid' => $subId,
            ':date' => $date,
            ':slot' => $slot
        ])->fetchColumn() > 0;
    }

    public function getPendingSubstitutions($userId) {
        $sql = "SELECT ls.*, ls.date as leave_date, ls.hour_slot as hour, l.leave_type, l.start_date, l.end_date, l.reason, u.name as faculty_name 
                FROM leave_substitutions ls
                JOIN leave_requests l ON ls.leave_request_id = l.id
                JOIN users u ON l.user_id = u.id
                WHERE ls.substitute_user_id = :uid AND ls.status = 'PENDING'";
        return $this->db->query($sql, [':uid' => $userId])->fetchAll();
    }

    public function updateSubstitutionStatus($id, $userId, $status) {
        $sql = "UPDATE leave_substitutions SET status = :status WHERE id = :id AND substitute_user_id = :uid";
        $this->db->query($sql, [':status'=>$status, ':id'=>$id, ':uid'=>$userId]);
    }

    public function getSubstitutionSummary($leaveId) {
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'ACCEPTED' THEN 1 ELSE 0 END) as accepted
                FROM leave_substitutions WHERE leave_request_id = :lid";
        return $this->db->query($sql, [':lid' => $leaveId])->fetch();
    }
    public function findById($id) {
        return $this->db->query("SELECT * FROM leave_requests WHERE id = :id", [':id' => $id])->fetch();
    }

    public function getPendingHod($dept) {
        $sql = "SELECT l.*, u.name as faculty_name, u.department,
                (SELECT COUNT(*) FROM leave_substitutions ls WHERE ls.leave_request_id = l.id) as total_subs,
                (SELECT COUNT(*) FROM leave_substitutions ls WHERE ls.leave_request_id = l.id AND ls.status = 'ACCEPTED') as accepted_subs
                FROM leave_requests l 
                JOIN users u ON l.user_id = u.id 
                WHERE l.hod_status = 'Pending' AND u.department = :dept";
        return $this->db->query($sql, [':dept' => $dept])->fetchAll();
    }

    public function getPendingPrincipal() {
        $sql = "SELECT l.*, u.name as faculty_name, u.department FROM leave_requests l 
                JOIN users u ON l.user_id = u.id 
                WHERE l.hod_status = 'Approved' AND l.principal_status = 'Pending'";
        return $this->db->query($sql)->fetchAll();
    }

    public function updateStatus($id, $column, $status) {
        $allowed = ['hod_status', 'principal_status'];
        if (!in_array($column, $allowed)) throw new Exception("Invalid status column");
        
        $sql = "UPDATE leave_requests SET $column = :status WHERE id = :id";
        $this->db->query($sql, [':status' => $status, ':id' => $id]);
    }

    public function getUserLeaves($userId) {
        $sql = "SELECT l.*, 
                (SELECT GROUP_CONCAT(CONCAT(class_name, ' (', subject_code, ') - H', hour_slot) SEPARATOR ' | ') 
                 FROM leave_substitutions ls WHERE ls.leave_request_id = l.id) as arrangements
                FROM leave_requests l 
                WHERE l.user_id = :uid 
                ORDER BY l.created_at DESC";
        return $this->db->query($sql, [':uid' => $userId])->fetchAll();
    }
}

