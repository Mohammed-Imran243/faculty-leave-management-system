<?php
header('Content-Type: application/json');
require_once '../../includes/config.php';
require_once '../../includes/auth_guard.php';
require_once '../../includes/helpers.php';
require_once '../../includes/audit.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '/';

$user = $global_user;

if ($method === 'POST' && $path === '/apply') {
    apply_outpass($conn, $user);
} elseif ($method === 'GET' && $path === '/my') {
    get_my_outpasses($conn, $user);
} elseif ($method === 'GET' && $path === '/pending/hod') {
    get_pending_hod($conn, $user);
} elseif ($method === 'GET' && $path === '/pending/principal') {
    get_pending_principal($conn, $user);
} elseif ($method === 'PUT' && preg_match('/^\/(\d+)\/approve\/hod$/', $path, $matches)) {
    approve_outpass($conn, $user, $matches[1], 'hod');
} elseif ($method === 'PUT' && preg_match('/^\/(\d+)\/approve\/principal$/', $path, $matches)) {
    approve_outpass($conn, $user, $matches[1], 'principal');
} else {
    http_response_code(404);
}

function apply_outpass($conn, $user) {
    // Role validation: Only teaching roles can apply
    if (!isTeachingRole($user['role']) && $user['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(["error" => "Only faculty/teaching staff can apply for outpasses."]);
        return;
    }

    $data = json_decode(file_get_contents("php://input"));
    
    if (!isset($data->outpass_date) || !isset($data->out_time) || !isset($data->reason)) {
        http_response_code(400);
        echo json_encode(["error" => "Missing required fields"]);
        return;
    }

    // Determine target user and override flag
    $target_user_id = $user['id'];
    $is_override = false;
    
    if (strtolower($user['role']) === 'admin') {
        if (isset($data->user_id)) {
            $target_user_id = $data->user_id;
        }
        if (isset($data->is_override) && $data->is_override) {
            $is_override = true;
        }
    }

    if (!$is_override) {
        // Fetch dynamic limit from database
        $stmtRule = $conn->prepare("SELECT rule_value FROM leave_rules WHERE rule_name = 'permission_outpass_limit'");
        $stmtRule->execute();
        $rule = $stmtRule->fetch();
        $limit = $rule ? (int)$rule['rule_value'] : 2;

        // Limit Validation: Max dynamic limit permission OR outpass per month
        $month = date('m', strtotime($data->outpass_date));
        $year = date('Y', strtotime($data->outpass_date));
        
        $stmtLimit = $conn->prepare("SELECT COUNT(*) as used_count FROM faculty_outpasses WHERE user_id = ? AND MONTH(outpass_date) = ? AND YEAR(outpass_date) = ? AND status != 'Rejected'");
        $stmtLimit->execute([$target_user_id, $month, $year]);
        $usage = $stmtLimit->fetch();
        
        $stmtPermLimit = $conn->prepare("SELECT COUNT(*) as used_count FROM faculty_permissions WHERE user_id = ? AND MONTH(permission_date) = ? AND YEAR(permission_date) = ? AND status != 'Rejected'");
        $stmtPermLimit->execute([$target_user_id, $month, $year]);
        $permUsage = $stmtPermLimit->fetch();
        
        if (($usage['used_count'] + $permUsage['used_count']) >= $limit) {
            http_response_code(400);
            echo json_encode(["error" => "Policy Limit Exceeded: Max {$limit} Permission OR Outpass per month allowed."]);
            return;
        }

        // DUPLICATE PROTECTION: Check if same request exists in last 1 minute
        $stmtDup = $conn->prepare("SELECT COUNT(*) FROM faculty_outpasses WHERE user_id = ? AND outpass_date = ? AND out_time = ? AND created_at > (NOW() - INTERVAL 1 MINUTE)");
        $stmtDup->execute([$target_user_id, $data->outpass_date, $data->out_time]);
        if ($stmtDup->fetchColumn() > 0) {
            http_response_code(409);
            echo json_encode(["error" => "Duplicate submission detected. Please wait a moment."]);
            return;
        }
    }

    $in_time = (isset($data->in_time) && trim($data->in_time) !== '') ? $data->in_time : null;

    try {
        $stmt = $conn->prepare("INSERT INTO faculty_outpasses (user_id, outpass_date, out_time, in_time, reason, status, is_override) VALUES (?, ?, ?, ?, ?, 'Pending_HOD', ?)");
        $stmt->execute([
            $target_user_id,
            $data->outpass_date,
            $data->out_time,
            $in_time,
            $data->reason,
            $is_override ? 1 : 0
        ]);
        
        echo json_encode(["message" => "Outpass applied successfully"]);
    } catch(Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => "Database Error: " . $e->getMessage()]);
    }
}

function get_my_outpasses($conn, $user) {
    $stmt = $conn->prepare("SELECT * FROM faculty_outpasses WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user['id']]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
}

function get_pending_hod($conn, $user) {
    if (!isAdministrativeRole($user['role'])) {
        http_response_code(403);
        echo json_encode(["error" => "Unauthorized"]);
        return;
    }
    $stmt = $conn->prepare("
        SELECT o.*, u.name, u.department 
        FROM faculty_outpasses o 
        JOIN users u ON o.user_id = u.id 
        WHERE o.status = 'Pending_HOD' AND u.department = ?
        ORDER BY o.created_at DESC
    ");
    $stmt->execute([$user['department']]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
}

function get_pending_principal($conn, $user) {
    if (strtolower($user['role']) !== 'principal' && strtolower($user['role']) !== 'admin') {
        http_response_code(403);
        echo json_encode(["error" => "Unauthorized"]);
        return;
    }
    $stmt = $conn->prepare("
        SELECT o.*, u.name, u.department 
        FROM faculty_outpasses o 
        JOIN users u ON o.user_id = u.id 
        WHERE o.status = 'Pending_Principal'
        ORDER BY o.created_at DESC
    ");
    $stmt->execute();
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
}

function approve_outpass($conn, $user, $id, $level) {
    $data = json_decode(file_get_contents("php://input"));
    $status = isset($data->status) ? $data->status : '';
    
    if (!in_array($status, ['Approved', 'Rejected'])) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid status. Must be Approved or Rejected"]);
        return;
    }

    $targetStatus = '';
    if ($level === 'hod' && (strtolower($user['role']) === 'hod' || strtolower($user['role']) === 'admin')) {
        $targetStatus = ($status === 'Approved') ? 'Pending_Principal' : 'Rejected';
    } elseif ($level === 'principal' && (strtolower($user['role']) === 'principal' || strtolower($user['role']) === 'admin')) {
        $targetStatus = ($status === 'Approved') ? 'Approved' : 'Rejected';
    } else {
        http_response_code(403);
        echo json_encode(["error" => "Unauthorized"]);
        return;
    }

    try {
        // SECURITY ENHANCEMENT: Verify department mismatch for HOD
        if ($level === 'hod' && strtolower($user['role']) !== 'admin') {
            $stmtDept = $conn->prepare("SELECT u.department FROM faculty_outpasses o JOIN users u ON o.user_id = u.id WHERE o.id = ?");
            $stmtDept->execute([$id]);
            $dept = $stmtDept->fetchColumn();
            if ($dept !== $user['department']) {
                http_response_code(403);
                echo json_encode(["error" => "Unauthorized: Department mismatch."]);
                return;
            }
        }

        $stmt = $conn->prepare("UPDATE faculty_outpasses SET status = ? WHERE id = ?");
        $stmt->execute([$targetStatus, $id]);
        
        logAudit($conn, $user['id'], "OUTPASS_".strtoupper($level)."_".strtoupper($status), ["outpass_id" => $id]);
        
        $msg = "Outpass " . ($status === 'Approved' ? "approved" : "rejected") . " by " . strtoupper($level);
        echo json_encode(["message" => $msg]);
    } catch(Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => "Database Error: " . $e->getMessage()]);
    }
}
