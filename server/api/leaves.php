<?php
require_once '../config.php';
require_once '../SimpleJWT.php';
require_once '../helpers.php';
require_once '../audit.php';

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

    $duration_type = isset($data->duration_type) ? $data->duration_type : 'Days';
    $selected_hours = isset($data->selected_hours) ? $data->selected_hours : null;
    $substitutions = isset($data->substitutions) ? $data->substitutions : []; // Array of {hour, substitute_id} or just {substitute_id} depending on logic

    try {
        $conn->beginTransaction();

        $stmt = $conn->prepare("INSERT INTO leave_requests (user_id, leave_type, start_date, end_date, reason, duration_type, selected_hours) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $user['id'], 
            $data->leave_type, 
            $data->start_date, 
            $data->end_date, 
            $data->reason,
            $duration_type,
            $selected_hours
        ]);
        $leave_id = $conn->lastInsertId();

        // Handle Substitutions
        foreach ($substitutions as $sub) {
            $hour = isset($sub->hour) ? $sub->hour : 0; // 0 for full day?
            $sub_id = $sub->substitute_id;
            
            $stmt = $conn->prepare("INSERT INTO leave_substitutions (leave_request_id, date, hour_slot, substitute_user_id, status) VALUES (?, ?, ?, ?, 'PENDING')");
            // Assuming single date for hourly, or we duplicate for date range.
            // For MVP/V2, let's assume 'date' is start_date for hourly. 
            // If full day, we might need multiple entries or just one generic 'All Day' entry (slot 0 or null). 
            // Schema has `date DATE NOT NULL, hour_slot INT NOT NULL`.
            // Let's use start_date and slot 0 for Full Day if needed, or 1 for Hour.
            $stmt->execute([$leave_id, $data->start_date, $hour, $sub_id]);

            // Notify Substitute
            create_notification($conn, $sub_id, "You have a substitution request from " . $user['name']);
        }

        $conn->commit();
        logAudit($conn, $user['id'], 'LEAVE_APPLIED', ['leave_id'=>$leave_id]);
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
    $data = json_decode(file_get_contents("php://input")); // { "status": "ACCEPTED" or "REJECTED" }
    
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
    create_notification($conn, $leave['user_id'], "Your substitution request was " . $data->status . " by " . $user['name']);
    
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
    create_notification($conn, $leave['user_id'], "Your leave request was " . $data->status . " by HoD");
    
    logAudit($conn, $user['id'], 'HOD_ACTION', ['leave_id'=>$id, 'status'=>$data->status]);

    echo json_encode(["message" => "HoD Status Updated"]);
}

function approve_principal($conn, $user, $id) {
    if ($user['role'] !== 'principal') return http_response_code(403);
    $data = json_decode(file_get_contents("php://input")); 
    
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
     create_notification($conn, $leave['user_id'], "Your leave request was " . $data->status . " by Principal. Final Decision.");
     
     logAudit($conn, $user['id'], 'PRINCIPAL_ACTION', ['leave_id'=>$id, 'status'=>$data->status]);

    // Generate PDF URL
    $pdfUrl = "http://localhost/faculty-system/server/api/generate_pdf.php?id=" . $id;

    echo json_encode(["message" => "Principal Status Updated", "pdf_url" => $pdfUrl]);
}

