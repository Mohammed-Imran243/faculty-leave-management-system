<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../core/auth_guard.php';

use App\Repositories\LeaveRepository;
use App\Repositories\RuleRepository;
use App\Repositories\NotificationRepository;
use App\Core\Database;

$method = $_SERVER['REQUEST_METHOD'];
$path = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '/';

$leaveRepo = new LeaveRepository();
$ruleRepo = new RuleRepository();
$notifRepo = new NotificationRepository();
$db = Database::getInstance();

try {
    // Router logic similarly to before but using repositories
    if ($method === 'POST' && $path === '/apply') {
        $data = json_decode(file_get_contents("php://input"), true);
        handleApplyLeave($data, $global_user, $leaveRepo, $ruleRepo, $notifRepo, $db);
        
    } elseif ($method === 'GET' && $path === '/my-leaves') {
        echo json_encode($leaveRepo->getUserLeaves($global_user['id']));
        
    } elseif ($method === 'GET' && $path === '/substitutions/pending') {
        echo json_encode($leaveRepo->getPendingSubstitutions($global_user['id']));
        
    } elseif ($method === 'PUT' && preg_match('/^\/substitutions\/(\d+)\/respond$/', $path, $matches)) {
        $data = json_decode(file_get_contents("php://input"), true);
        $leaveRepo->updateSubstitutionStatus($matches[1], $global_user['id'], $data['status']);
        echo json_encode(["message" => "Substitution updated"]);
        
    } elseif ($method === 'GET' && $path === '/pending/hod') {
        echo json_encode($leaveRepo->getPendingHod($global_user['department']));
        
    } elseif ($method === 'GET' && $path === '/pending/principal') {
        echo json_encode($leaveRepo->getPendingPrincipal());
        
    } elseif ($method === 'PUT' && preg_match('/^\/(\d+)\/approve\/hod$/', $path, $matches)) {
        $data = json_decode(file_get_contents("php://input"), true);
        handleHodApprove($matches[1], $data, $global_user, $leaveRepo, $notifRepo);
        
    } elseif ($method === 'PUT' && preg_match('/^\/(\d+)\/approve\/principal$/', $path, $matches)) {
        $data = json_decode(file_get_contents("php://input"), true);
        handlePrincipalApprove($matches[1], $data, $global_user, $leaveRepo, $notifRepo);
        
    } else {
        http_response_code(404);
        echo json_encode(["error" => "Route or method not found."]);
    }
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}

function handleHodApprove($id, $data, $user, $leaveRepo, $notifRepo) {
    if (strtolower($user['role']) !== 'hod') throw new Exception("Unauthorized");
    
    // Check subs
    $summary = $leaveRepo->getSubstitutionSummary($id);
    if ($summary['total'] > 0 && $summary['total'] != $summary['accepted']) {
        throw new Exception("Cannot approve. Pending substitutions exist.");
    }

    $leaveRepo->updateStatus($id, 'hod_status', $data['status']);
    
    // Notify
    $leave = $leaveRepo->findById($id);
    $notifRepo->create($leave['user_id'], "Leave " . $data['status'] . " by HoD", 'LEAVE_UPDATE');
    echo json_encode(["message" => "HoD approval updated"]);
}

function handlePrincipalApprove($id, $data, $user, $leaveRepo, $notifRepo) {
    if (strtolower($user['role']) !== 'principal') throw new Exception("Unauthorized");
    
    $leaveRepo->updateStatus($id, 'principal_status', $data['status']);
    
    $leave = $leaveRepo->findById($id);
    $notifRepo->create($leave['user_id'], "Leave " . $data['status'] . " by Principal", 'LEAVE_UPDATE');
    echo json_encode(["message" => "Principal approval updated"]);
}


function handleApplyLeave($data, $user, $leaveRepo, $ruleRepo, $notifRepo, $db) {
    // Basic Validation
    if (empty($data['start_date']) || empty($data['end_date'])) {
        throw new Exception("Missing dates");
    }

    $start = new DateTime($data['start_date']);
    $end = new DateTime($data['end_date']);
    $total_days = $start->diff($end)->days + 1;

    // Check Limits
    $limitRule = $ruleRepo->findByName($data['leave_type'] . ' Limit');
    $limit = $limitRule ? (float)$limitRule['rule_value'] : 12;
    $used = $leaveRepo->getUsedDays($user['id'], $data['leave_type'], $start->format('m'), $start->format('Y'));

    if (($used + $total_days) > $limit && !($data['is_override'] ?? false)) {
        throw new Exception("Policy limit exceeded: Max $limit days.");
    }

    // Transactional logic
    $db->beginTransaction();
    try {
        $data['user_id'] = $user['id'];
        $leaveId = $leaveRepo->create($data);

        if (!empty($data['substitutions'])) {
            foreach ($data['substitutions'] as $sub) {
                $leaveRepo->addSubstitution($leaveId, $data['start_date'], $sub['hour'], $sub['substitute_id']);
                $notifRepo->create($sub['substitute_id'], "New substitution request from " . $user['name'], 'SUBSTITUTION');
            }
        }
        $db->commit();
        echo json_encode(["message" => "Leave applied successfully", "id" => $leaveId]);
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}
