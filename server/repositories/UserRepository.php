<?php
namespace App\Repositories;

use App\Core\Database;

class UserRepository {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function findById($id) {
        $stmt = $this->db->query("SELECT * FROM users WHERE id = :id", [':id' => $id]);
        return $stmt->fetch();
    }

    public function findByUsername($username) {
        $stmt = $this->db->query("SELECT * FROM users WHERE username = :login OR email = :login", [':login' => $username]);
        return $stmt->fetch();
    }

    public function getAllPaginated($page = 1, $limit = 20) {
        return $this->search('', '', $page, $limit);
    }

    public function create($data) {
        if (isset($data['role'])) $this->validateRole($data['role']);
        
        $sql = "INSERT INTO users (name, username, email, password_hash, role, employee_code, designation, department) 
                VALUES (:name, :username, :email, :pass, :role, :emp, :desig, :dept)";
        
        $this->db->query($sql, [
            ':name'     => $data['name'],
            ':username' => $data['username'],
            ':email'    => $data['email'] ?? '',
            ':pass'     => $data['password_hash'],
            ':role'     => $data['role'] ?? 'faculty',
            ':emp'      => $data['employee_code'] ?? null,
            ':desig'    => $data['designation'] ?? null,
            ':dept'     => $data['department'] ?? null
        ]);
        
        return $this->db->getConnection()->lastInsertId();
    }

    public function update($id, $data) {
        $fields = [];
        $params = [':id' => $id];
        
        foreach ($data as $key => $value) {
            if ($key === 'id') continue;
            if ($key === 'role') $this->validateRole($value);
            $fields[] = "$key = :$key";
            $params[":$key"] = $value;
        }
        
        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = :id";
        $this->db->query($sql, $params);
    }

    public function delete($id) {
        $this->db->query("DELETE FROM users WHERE id = :id", [':id' => $id]);
    }

    public function search($query, $dept = '', $page = 1, $limit = 20) {
        $offset = ($page - 1) * $limit;
        $conditions = [];
        $params = [];

        if ($query) {
            $conditions[] = "(name LIKE :query OR username LIKE :query OR employee_code LIKE :query)";
            $params[':query'] = "%$query%";
        }
        if ($dept) {
            $conditions[] = "department = :dept";
            $params[':dept'] = $dept;
        }

        $where = !empty($conditions) ? "WHERE " . implode(' AND ', $conditions) : "";
        
        $countSql = "SELECT COUNT(*) FROM users $where";
        $total = $this->db->query($countSql, $params)->fetchColumn();

        $sql = "SELECT id, name, username, employee_code, designation, role, department 
                FROM users $where ORDER BY name ASC LIMIT :limit OFFSET :offset";
        
        $params[':limit'] = (int)$limit;
        $params[':offset'] = (int)$offset;
        
        $stmt = $this->db->getConnection()->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val, is_int($val) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
        $stmt->execute();
        
        return [
            'users' => $stmt->fetchAll(),
            'total' => (int)$total,
            'page' => (int)$page,
            'limit' => (int)$limit,
            'total_pages' => ceil($total / $limit)
        ];
    }

    private function validateRole($role) {
        $allowed = ['admin', 'faculty', 'hod', 'principal', 'assistant professor (ap)', 'associate professor', 'professor', 'officer'];
        if (!in_array(strtolower($role), $allowed)) {
            throw new \Exception("Invalid role: $role");
        }
    }
}
