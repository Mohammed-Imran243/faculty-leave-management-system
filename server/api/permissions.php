<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../core/bootstrap.php';
require_once __DIR__ . '/../core/auth_guard.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/audit.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '/';

$user = $global_user;

if ($method === 'POST' && $path === '/apply') {
    apply_permission($conn, $user);
} elseif ($method === 'GET' && $path === '/my') {
    get_my_permissions($conn, $user);
} elseif ($method === 'GET' && $path === '/pending/hod') {
    get_pending_hod($conn, $user);
} elseif ($method === 'GET' && $path === '/pending/principal') {
    get_pending_principal($conn, $user);
} elseif ($method === 'PUT' && preg_match('/^\/(\d+)\/approve\/hod$/', $path, $matches)) {
    approve_permission($conn, $user, $matches[1], 'hod');
} elseif ($method === 'PUT' && preg_match('/^\/(\d+)\/approve\/principal$/', $path, $matches)) {
    approve_permission($conn, $user, $matches[1], 'principal');
} else {
    http_response_code(404);
}

function apply_permission($conn, $user) {
    // Role validation: Only teaching roles can apply
    if (!isTeachingRole($user['role']) && $user['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(["error" => "Only faculty/teaching staff can apply for permissions."]);
        return;
    }

    $data = json_decode(file_get_contents("php://input"));
    
    if (!isset($data->permission_date) || !isset($data->start_time) || !isset($data->end_time) || !isset($data->reason)) {
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

    // Exact Time Validation dynamically configured
    // Strip seconds if present (HTML input type="time" might send them)
    $start = substr($data->start_time, 0, 5); 
    $end = substr($data->end_time, 0, 5);

    if (!$is_override) {
        // Fetch dynamic time slots and merged limit
        $timeRulesStmt = $conn->prepare("SELECT rule_name, rule_value FROM leave_rules WHERE rule_name IN ('permission_start_time', 'permission_end_time', 'permission_evening_start', 'permission_evening_end', 'permission_outpass_limit')");
        $timeRulesStmt->execute();
        $rulesData = $timeRulesStmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $p_start = isset($rulesData['permission_start_time']) ? (int)$rulesData['permission_start_time'] : 930;
        $p_end = isset($rulesData['permission_end_time']) ? (int)$rulesData['permission_end_time'] : 1030;
        $pe_start = isset($rulesData['permission_evening_start']) ? (int)$rulesData['permission_evening_start'] : 1630;
        $pe_end = isset($rulesData['permission_evening_end']) ? (int)$rulesData['permission_evening_end'] : 1730;
        
        $req_start = (int)str_replace(':', '', $start);
        $req_end = (int)str_replace(':', '', $end);

        $validTime = false;
        // Check if requested time matches configured intervals exactly (or fits inside, but the UX allows specific ranges usually. If they just need to match:)
        if (($req_start === $p_start && $req_end === $p_end) || ($req_start === $pe_start && $req_end === $pe_end)) {
            $validTime = true;
        }

        if (!$validTime) {
            http_response_code(400);
            $err_str = sprintf("Invalid Time: Permissions are only allowed from %04d to %04d OR %04d to %04d.", $p_start, $p_end, $pe_start, $pe_end);
            $err_str = preg_replace('/(\d{2})(\d{2})/', '$1:$2', $err_str);
            echo json_encode(["error" => $err_str]);
            return;
        }

        $limit = isset($rulesData['permission_outpass_limit']) ? (int)$rulesData['permission_outpass_limit'] : 2;

        // Limit Validation: Max dynamic limit permission OR outpass per month
        $month = date('m', strtotime($data->permission_date));
        $year = date('Y', strtotime($data->permission_date));
        
        $stmtLimit = $conn->prepare("SELECT COUNT(*) as used_count FROM faculty_permissions WHERE user_id = ? AND MONTH(permission_date) = ? AND YEAR(permission_date) = ? AND status != 'Rejected'");
        $stmtLimit->execute([$target_user_id, $month, $year]);
        $usage = $stmtLimit->fetch();
        
        $stmtOutpassLimit = $conn->prepare("SELECT COUNT(*) as used_count FROM faculty_outpasses WHERE user_id = ? AND MONTH(outpass_date) = ? AND YEAR(outpass_date) = ? AND status != 'Rejected'");
        $stmtOutpassLimit->execute([$target_user_id, $month, $year]);
        $outpassUsage = $stmtOutpassLimit->fetch();
        
        if (($usage['used_count'] + $outpassUsage['used_count']) >= $limit) {
            http_response_code(400);
            echo json_encode(["error" => "Policy Limit Exceeded: Max {$limit} Permission OR Outpass per month allowed."]);
            return;
        }

        // DUPLICATE PROTECTION: Check if same request exists in last 1 minute
        $stmtDup = $conn->prepare("SELECT COUNT(*) FROM faculty_permissions WHERE user_id = ? AND permission_date = ? AND start_time = ? AND created_at > (NOW() - INTERVAL 1 MINUTE)");
        $stmtDup->execute([$target_user_id, $data->permission_date, $data->start_time]);
        if ($stmtDup->fetchColumn() > 0) {
            http_response_code(409);
            echo json_encode(["error" => "Duplicate submission detected. Please wait a moment."]);
            return;
        }
    }

    try {
        $stmt = $conn->prepare("INSERT INTO faculty_permissions (user_id, permission_date, start_time, end_time, reason, status, is_override) VALUES (?, ?, ?, ?, ?, 'Pending_HOD', ?)");
        $stmt->execute([
            $target_user_id,
            $data->permission_date,
            $data->start_time,
            $data->end_time,
            $data->reason,
            $is_override ? 1 : 0
        ]);
        
        echo json_encode(["message" => "Permission applied successfully"]);
    } catch(Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => "Database Error: " . $e->getMessage()]);
    }
}

function get_my_permissions($conn, $user) {
    $stmt = $conn->prepare("SELECT * FROM faculty_permissions WHERE user_id = ? ORDER BY created_at DESC");
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
        SELECT p.*, u.name, u.department 
        FROM faculty_permissions p 
        JOIN users u ON p.user_id = u.id 
        WHERE p.status = 'Pending_HOD' AND u.department = ?
        ORDER BY p.created_at DESC
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
        SELECT p.*, u.name, u.department 
        FROM faculty_permissions p 
        JOIN users u ON p.user_id = u.id 
        WHERE p.status = 'Pending_Principal'
        ORDER BY p.created_at DESC
    ");
    $stmt->execute();
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
}

function approve_permission($conn, $user, $id, $level) {
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
            $stmtDept = $conn->prepare("SELECT u.department FROM faculty_permissions p JOIN users u ON p.user_id = u.id WHERE p.id = ?");
            $stmtDept->execute([$id]);
            $dept = $stmtDept->fetchColumn();
            if ($dept !== $user['department']) {
                http_response_code(403);
                echo json_encode(["error" => "Unauthorized: Department mismatch."]);
                return;
            }
        }

        $stmt = $conn->prepare("UPDATE faculty_permissions SET status = ? WHERE id = ?");
        $stmt->execute([$targetStatus, $id]);
        
        logAudit($conn, $user['id'], "PERMISSION_".strtoupper($level)."_".strtoupper($status), ["permission_id" => $id]);
        
        $msg = "Permission " . ($status === 'Approved' ? "approved" : "rejected") . " by " . strtoupper($level);
        echo json_encode(["message" => $msg]);
    } catch(Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => "Database Error: " . $e->getMessage()]);
    }
}
