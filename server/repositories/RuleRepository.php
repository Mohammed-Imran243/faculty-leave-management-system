<?php
namespace App\Repositories;

use App\Core\Database;

class RuleRepository {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function getAll() {
        return $this->db->query("SELECT * FROM leave_rules ORDER BY id ASC")->fetchAll();
    }

    public function findByName($name) {
        return $this->db->query("SELECT * FROM leave_rules WHERE rule_name = :name", [':name' => $name])->fetch();
    }

    public function update($id, $value) {
        $this->db->query("UPDATE leave_rules SET rule_value = :val WHERE id = :id", [
            ':val' => $value,
            ':id'  => $id
        ]);
    }
}
