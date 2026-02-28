<?php
require_once '../config.php';

require_once '../libs/SimpleJWT.php';
require_once '../libs/helpers.php';
require_once '../libs/audit.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '/';

$token = JWT::get_bearer_token();
$user = $token ? JWT::decode($token) : null;

if (!$user) {
    http_response_code(403);
    echo json_encode(["error" => "Unauthorized"]);
    exit();
}

// Router
if ($method === 'POST' && $path === '/apply') {
    apply_leave($conn, $user);
} elseif ($method === 'GET' && $path === '/my-leaves') {
    get_my_leaves($conn, $user);
} elseif ($method === 'GET' && $path === '/substitutions/pending') {
    get_pending_substitutions($conn, $user);
} elseif ($method === 'PUT' && preg_match('/^\/substitutions\/(\d+)\/respond$/', $path, $matches)) {
    action_substitution($conn, $user, $matches[1]);
} elseif ($method === 'GET' && $path === '/pending/hod') {
    get_pending_hod($conn, $user);
} elseif ($method === 'GET' && $path === '/pending/principal') {
    get_pending_principal($conn, $user);
} elseif ($method === 'PUT' && preg_match('/^\/(\d+)\/approve\/hod$/', $path, $matches)) {
    approve_hod($conn, $user, $matches[1]);
} elseif ($method === 'PUT' && preg_match('/^\/(\d+)\/approve\/principal$/', $path, $matches)) {
    approve_principal($conn, $user, $matches[1]);
} else {
    http_response_code(404);
}

function apply_leave($conn, $user) {
    $data = json_decode(file_get_contents("php://input"));
    
    // Basic Validation
    if (!isset($data->leave_type) || !isset($data->start_date) || !isset($data->end_date)) {
        http_response_code(400);
        echo json_encode(["error" => "Missing required fields"]);
        return;
    }

    if ($data->start_date > $data->end_date) {
        http_response_code(400);
        echo json_encode(["error" => "End Date cannot be before Start Date"]);
        return;
    }

    // Force Duration Type to 'Days'
    $duration_type = 'Days';
    $selected_hours = null; // No longer used

    // Calculate Total Days
    $start = new DateTime($data->start_date);
    $end = new DateTime($data->end_date);
    $interval = $start->diff($end);
    $total_days = $interval->days + 1;

    $substitutions = isset($data->substitutions) ? $data->substitutions : [];

    // Validation: Substitution Rules
    if ($total_days <= 4) {
        // Optional substitutions: No strict count check required.
    } else {
        // > 4 Days: substitutions are not required/stored
        $substitutions = [];
    }

        try {
        $conn->beginTransaction();

        $stmt = $conn->prepare("INSERT INTO leave_requests (user_id, leave_type, start_date, end_date, reason, duration_type, selected_hours, hod_status, principal_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $user['id'],
            $data->leave_type,
            $data->start_date,
            $data->end_date,
            $data->reason,
            $duration_type,
            $selected_hours,
            ($user['role'] === 'hod' ? 'Approved' : 'Pending'), // Auto-approve HoD level if applicant is HoD
            'Pending' // Principal Status
        ]);
        $leave_id = $conn->lastInsertId();

        // Handle Substitutions
        // Frontend sends: [{ substitute_id: 123, hour: 1-16 }]
        // Map 1-8 -> Day 1, 9-16 -> Day 2
        foreach ($substitutions as $sub) {
            $hour_idx = isset($sub->hour) ? $sub->hour : 1;
            $sub_id = $sub->substitute_id;

            if ($sub_id == $user['id']) {
                throw new Exception("Cannot substitute for yourself");
            }
            
            // Determine Date and Period
            // Day 1: 1-8
            // Day 2: 9-16
            // We can calculate offset: floor((hour-1)/8)
            
            $day_offset = floor(($hour_idx - 1) / 8);
            $period = (($hour_idx - 1) % 8) + 1;

            $date_obj = clone $start;
            if ($day_offset > 0) {
                $date_obj->modify("+$day_offset day");
            }
            $sub_date = $date_obj->format('Y-m-d');
            
            $stmt = $conn->prepare("INSERT INTO leave_substitutions (leave_request_id, date, hour_slot, substitute_user_id, status) VALUES (?, ?, ?, ?, 'PENDING')");
            $stmt->execute([$leave_id, $sub_date, $period, $sub_id]);

            // Notify Substitute
            create_notification($conn, $sub_id, "You have a substitution request from " . $user['name'] . " for Period $period on $sub_date", 'SUBSTITUTION_REQUEST');
        }

        $conn->commit();
        logAudit($conn, $user['id'], 'LEAVE_APPLIED', ['leave_id'=>$leave_id, 'days'=>$total_days]);
        echo json_encode(["message" => "Leave applied successfully"]);
    } catch(Exception $e) {
        $conn->rollBack();
        http_response_code(500);
        echo json_encode(["error" => "Database Error: " . $e->getMessage()]);
    }
}

function get_my_leaves($conn, $user) {
    // Also fetch substitution status summary?
    $stmt = $conn->prepare("SELECT * FROM leave_requests WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user['id']]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
}

function get_pending_substitutions($conn, $user) {
    // Requests where I am the substitute and status is PENDING
    $sql = "SELECT ls.*, l.leave_type, l.start_date, l.end_date, l.reason, u.name as requester_name 
            FROM leave_substitutions ls
            JOIN leave_requests l ON ls.leave_request_id = l.id
            JOIN users u ON l.user_id = u.id
            WHERE ls.substitute_user_id = ? AND ls.status = 'PENDING'";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$user['id']]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
}

function action_substitution($conn, $user, $id) {
    $data = json_decode(file_get_contents("php://input"));
    if (!$data || !isset($data->status) || !in_array($data->status, ['ACCEPTED', 'REJECTED'])) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid status"]);
        return;
    }
    // Check ownership
    $stmt = $conn->prepare("SELECT * FROM leave_substitutions WHERE id = ? AND substitute_user_id = ?");
    $stmt->execute([$id, $user['id']]);
    $sub = $stmt->fetch();

    if (!$sub) {
        http_response_code(404);
        echo json_encode(["error" => "Substitution request not found"]);
        return;
    }

    $stmt = $conn->prepare("UPDATE leave_substitutions SET status = ? WHERE id = ?");
    $stmt->execute([$data->status, $id]);

    // Notify original requester
    // Fetch leave and user info
    $stmt = $conn->prepare("SELECT user_id FROM leave_requests WHERE id = ?");
    $stmt->execute([$sub['leave_request_id']]);
    $leave = $stmt->fetch();
    create_notification($conn, $leave['user_id'], "Your substitution request was " . $data->status . " by " . $user['name'], 'SUBSTITUTION_RESPONSE');
    
    logAudit($conn, $user['id'], 'SUBSTITUTION_ACTION', ['sub_id'=>$id, 'status'=>$data->status]);

    echo json_encode(["message" => "Substitution Query Updated"]);
}


// HoD: Get requests from their department
function get_pending_hod($conn, $user) {
    if ($user['role'] !== 'hod') return http_response_code(403);
    
    // Logic: Show requests where `hod_status` is Pending AND
    // (There are NO substitutions OR All substitutions are ACCEPTED)
    // This is a bit complex in SQL.
    
    $sql = "SELECT l.*, u.name, u.department,
            (SELECT COUNT(*) FROM leave_substitutions ls WHERE ls.leave_request_id = l.id) as total_subs,
            (SELECT COUNT(*) FROM leave_substitutions ls WHERE ls.leave_request_id = l.id AND ls.status = 'ACCEPTED') as accepted_subs
            FROM leave_requests l 
            JOIN users u ON l.user_id = u.id 
            WHERE l.hod_status = 'Pending' AND u.department = ?";
            
    // Filter in PHP or complex Having clause. Let's filter in PHP for clarity.
    $stmt = $conn->prepare($sql);
    $stmt->execute([$user['department']]);
    $all_leaves = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $ready_leaves = [];
    foreach($all_leaves as $leave) {
        if ($leave['total_subs'] == 0 || $leave['total_subs'] == $leave['accepted_subs']) {
             // Fetch sub details for display
             $stmtSub = $conn->prepare("SELECT ls.*, u.name as sub_name FROM leave_substitutions ls JOIN users u ON ls.substitute_user_id = u.id WHERE ls.leave_request_id = ?");
             $stmtSub->execute([$leave['id']]);
             $leave['substitutions'] = $stmtSub->fetchAll(PDO::FETCH_ASSOC);
             $ready_leaves[] = $leave;
        }
    }
    
    echo json_encode($ready_leaves);
}

// Principal: Get HoD-approved requests
function get_pending_principal($conn, $user) {
    if ($user['role'] !== 'principal') return http_response_code(403);

    $sql = "SELECT l.*, u.name, u.department FROM leave_requests l 
            JOIN users u ON l.user_id = u.id 
            WHERE l.hod_status = 'Approved' AND l.principal_status = 'Pending'";
    $stmt = $conn->query($sql);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
}

function approve_hod($conn, $user, $id) {
    if ($user['role'] !== 'hod') return http_response_code(403);
    $data = json_decode(file_get_contents("php://input")); // { "status": "Approved" }
    
    // STRICT CHECK: Ensure all substitutions are accepted
    $sqlCheck = "SELECT 
        (SELECT COUNT(*) FROM leave_substitutions WHERE leave_request_id = ?) as total,
        (SELECT COUNT(*) FROM leave_substitutions WHERE leave_request_id = ? AND status = 'ACCEPTED') as accepted";
    $stmtCheck = $conn->prepare($sqlCheck);
    $stmtCheck->execute([$id, $id]);
    $check = $stmtCheck->fetch();

    if ($check['total'] > 0 && $check['total'] != $check['accepted']) {
        http_response_code(400);
        echo json_encode(["error" => "Cannot approve. Pending substitutions exist."]);
        return;
    }

    $stmt = $conn->prepare("UPDATE leave_requests SET hod_status = ? WHERE id = ?");
    $stmt->execute([$data->status, $id]);
    
    // Insert Approval Record
    $action = ($data->status == 'Approved') ? 'APPROVED' : 'REJECTED';
    $stmt = $conn->prepare("INSERT INTO approvals (leave_request_id, approver_id, role_at_time, action) VALUES (?, ?, ?, ?)");
    $stmt->execute([$id, $user['id'], 'hod', $action]);

    // Notify User
    $stmt = $conn->prepare("SELECT user_id FROM leave_requests WHERE id = ?");
    $stmt->execute([$id]);
    $leave = $stmt->fetch();
    create_notification($conn, $leave['user_id'], "Your leave request was " . $data->status . " by HoD", 'LEAVE_STATUS_UPDATE');
    
    logAudit($conn, $user['id'], 'HOD_ACTION', ['leave_id'=>$id, 'status'=>$data->status]);

    echo json_encode(["message" => "HoD Status Updated"]);
}

function approve_principal($conn, $user, $id) {
    if ($user['role'] !== 'principal') return http_response_code(403);
    $data = json_decode(file_get_contents("php://input")); 
    
    // STRICT CHECK: HoD must have approved (unless the applicant is the HoD)
    // We check the current db state
    $stmtCheck = $conn->prepare("SELECT hod_status, user_id FROM leave_requests WHERE id = ?");
    $stmtCheck->execute([$id]);
    $current = $stmtCheck->fetch();

    // Get applicant role to see if they are HoD
    $stmtRole = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $stmtRole->execute([$current['user_id']]);
    $applicant = $stmtRole->fetch();

    if ($current['hod_status'] !== 'Approved' && $applicant['role'] !== 'hod') {
         http_response_code(400);
         echo json_encode(["error" => "Cannot approve. HoD approval missing."]);
         return;
    }

    $stmt = $conn->prepare("UPDATE leave_requests SET principal_status = ? WHERE id = ?");
    $stmt->execute([$data->status, $id]);

     // Insert Approval Record
     $action = ($data->status == 'Approved') ? 'APPROVED' : 'REJECTED';
     $stmt = $conn->prepare("INSERT INTO approvals (leave_request_id, approver_id, role_at_time, action) VALUES (?, ?, ?, ?)");
     $stmt->execute([$id, $user['id'], 'principal', $action]);

    // Notify User
     $stmt = $conn->prepare("SELECT user_id FROM leave_requests WHERE id = ?");
     $stmt->execute([$id]);
     $leave = $stmt->fetch();
     create_notification($conn, $leave['user_id'], "Your leave request was " . $data->status . " by Principal. Final Decision.", 'LEAVE_STATUS_UPDATE');
     
     logAudit($conn, $user['id'], 'PRINCIPAL_ACTION', ['leave_id'=>$id, 'status'=>$data->status]);

    // Generate PDF URL (base from config or current request)
    $apiDir = dirname($_SERVER['SCRIPT_NAME'] ?? '/server/api');
    $pdfUrl = ($base_url ?? '') . $apiDir . '/generate_pdf.php?id=' . $id;
    echo json_encode(["message" => "Principal Status Updated", "pdf_url" => $pdfUrl]);
}

