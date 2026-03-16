<?php
namespace App\Repositories;

use App\Core\Database;

class NotificationRepository {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function create($userId, $message, $type) {
        $sql = "INSERT INTO notifications (user_id, message, type, is_read, created_at) 
                VALUES (:uid, :msg, :type, 0, NOW())";
        $this->db->query($sql, [
            ':uid'  => $userId,
            ':msg'  => $message,
            ':type' => $type
        ]);
        return $this->db->getConnection()->lastInsertId();
    }

    public function getUnreadForUser($userId) {
        return $this->db->query("SELECT * FROM notifications WHERE user_id = :uid AND is_read = 0 ORDER BY created_at DESC", [':uid' => $userId])->fetchAll();
    }

    public function markAsRead($id, $userId) {
        $this->db->query("UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :uid", [':id' => $id, ':uid' => $userId]);
    }

    public function markAllRead($userId) {
        $this->db->query("UPDATE notifications SET is_read = 1 WHERE user_id = :uid AND is_read = 0", [':uid' => $userId]);
    }
}
