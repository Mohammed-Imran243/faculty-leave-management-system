<?php
namespace App\Repositories;

use App\Core\Database;

class LeaveRepository {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function getUsedDays($userId, $leaveType, $month, $year) {
        $sql = "SELECT SUM(DATEDIFF(end_date, start_date) + 1) as used_days 
                FROM leave_requests 
                WHERE user_id = :uid 
                AND leave_type = :type 
                AND MONTH(start_date) = :m 
                AND YEAR(start_date) = :y 
                AND hod_status != 'Rejected' 
                AND principal_status != 'Rejected'";
        
        return $this->db->query($sql, [
            ':uid'  => $userId,
            ':type' => $leaveType,
            ':m'    => $month,
            ':y'    => $year
        ])->fetchColumn() ?: 0;
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

    public function addSubstitution($leaveId, $date, $period, $subId) {
        $sql = "INSERT INTO leave_substitutions (leave_request_id, date, hour_slot, substitute_user_id, status) 
                VALUES (:lid, :date, :slot, :sid, 'PENDING')";
        $this->db->query($sql, [
            ':lid'  => $leaveId,
            ':date' => $date,
            ':slot' => $period,
            ':sid'  => $subId
        ]);
    }

    public function getPendingSubstitutions($userId) {
        $sql = "SELECT ls.*, l.leave_type, l.start_date, l.end_date, l.reason, u.name as requester_name 
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
        $sql = "SELECT l.*, u.name, u.department,
                (SELECT COUNT(*) FROM leave_substitutions ls WHERE ls.leave_request_id = l.id) as total_subs,
                (SELECT COUNT(*) FROM leave_substitutions ls WHERE ls.leave_request_id = l.id AND ls.status = 'ACCEPTED') as accepted_subs
                FROM leave_requests l 
                JOIN users u ON l.user_id = u.id 
                WHERE l.hod_status = 'Pending' AND u.department = :dept";
        return $this->db->query($sql, [':dept' => $dept])->fetchAll();
    }

    public function getPendingPrincipal() {
        $sql = "SELECT l.*, u.name, u.department FROM leave_requests l 
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
        $sql = "SELECT * FROM leave_requests WHERE user_id = :uid ORDER BY created_at DESC";
        return $this->db->query($sql, [':uid' => $userId])->fetchAll();
    }
}

