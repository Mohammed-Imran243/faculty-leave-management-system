<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../core/bootstrap.php';
require_once __DIR__ . '/../core/auth_guard.php';
require_once __DIR__ . '/../core/helpers.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '/';

$user = $global_user;

if ($method !== 'GET') {
    http_response_code(404);
    exit();
}

$response = [];
$month = date('m');
$year = date('Y');
$firstDay = date('Y-m-01');
$lastDay = date('Y-m-t');

function get_rule_limit($conn, $rule_name, $default) {
    $stmt = $conn->prepare("SELECT rule_value FROM leave_rules WHERE rule_name = ?");
    $stmt->execute([$rule_name]);
    $rule = $stmt->fetch();
    return $rule ? (int)$rule['rule_value'] : $default;
}

// --- Caching Logic ---
$cacheDir = __DIR__ . '/../cache';
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0777, true);
}

$cacheFile = $cacheDir . '/analytics_' . $user['id'] . '_' . $user['role'] . (isset($_GET['department']) ? '_' . $_GET['department'] : '') . '.json';
$cacheTime = 60; // 60 seconds

if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTime)) {
    header('X-Cache: HIT');
    echo file_get_contents($cacheFile);
    exit;
}
header('X-Cache: MISS');
// --- End Caching Logic ---

if (isTeachingRole($user['role'])) {
    // 1. Total leaves this Year (excluding Rejected/Cancelled)
    $stmt = $conn->prepare("SELECT COUNT(*) FROM leave_requests WHERE user_id = ? AND YEAR(start_date) = ? AND hod_status NOT IN ('Rejected', 'Cancelled') AND principal_status NOT IN ('Rejected', 'Cancelled')");
    $stmt->execute([$user['id'], $year]);
    $response['total_leaves'] = (int)$stmt->fetchColumn();

    // 2. Casual Leave Used this Year (excluding Rejected/Cancelled)
    $stmt = $conn->prepare("SELECT start_date, end_date FROM leave_requests WHERE user_id = ? AND leave_type = 'Casual' AND hod_status NOT IN ('Rejected', 'Cancelled') AND principal_status NOT IN ('Rejected', 'Cancelled')");
    $stmt->execute([$user['id']]);
    $leaves = $stmt->fetchAll();
    
    $cl_used = 0;
    foreach ($leaves as $l) {
        $start = new DateTime($l['start_date']);
        $end = new DateTime($l['end_date']);
        $curr = clone $start;
        while ($curr <= $end) {
            // Count for the entire year, excluding Sundays
            if ($curr->format('Y') == $year && $curr->format('N') != 7) {
                $cl_used++;
            }
            $curr->modify('+1 day');
        }
    }
    $response['casual_leave_used'] = $cl_used;

    // 3. Remaining Casual Leave
    $cl_limit = get_rule_limit($conn, 'Casual Leave Limit', 12);
    $response['remaining_casual_leave'] = max(0, $cl_limit - $cl_used);

    // 4. Permissions Used
    $stmt = $conn->prepare("SELECT COUNT(*) FROM faculty_permissions WHERE user_id = ? AND MONTH(permission_date) = ? AND YEAR(permission_date) = ? AND status != 'Rejected'");
    $stmt->execute([$user['id'], $month, $year]);
    $response['permissions_used'] = (int)$stmt->fetchColumn();

    // 5. Outpasses Used
    $stmt = $conn->prepare("SELECT COUNT(*) FROM faculty_outpasses WHERE user_id = ? AND MONTH(outpass_date) = ? AND YEAR(outpass_date) = ? AND status != 'Rejected'");
    $stmt->execute([$user['id'], $month, $year]);
    $response['outpasses_used'] = (int)$stmt->fetchColumn();

    // 6. Distribution (My Leave Breakdown)
    $stmt = $conn->prepare("SELECT leave_type, COUNT(*) as count FROM leave_requests WHERE user_id = ? AND MONTH(start_date) = ? AND YEAR(start_date) = ? AND hod_status != 'Rejected' AND principal_status != 'Rejected' GROUP BY leave_type");
    $stmt->execute([$user['id'], $month, $year]);
    $response['distribution'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 7. Pending Requests Breakdown (Faculty)
    // Wait for sub
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT l.id) 
        FROM leave_requests l
        JOIN leave_substitutions s ON l.id = s.leave_request_id 
        WHERE l.user_id = ? AND s.status = 'PENDING' AND l.hod_status = 'Pending'
    ");
    $stmt->execute([$user['id']]);
    $wait_sub = (int)$stmt->fetchColumn();

    // Wait for HoD (Substitutions are either cleared or none exist)
    $stmt = $conn->prepare("
        SELECT COUNT(l.id) 
        FROM leave_requests l
        WHERE l.user_id = ? AND l.hod_status = 'Pending'
        AND NOT EXISTS (
            SELECT 1 FROM leave_substitutions s WHERE s.leave_request_id = l.id AND s.status = 'PENDING'
        )
    ");
    $stmt->execute([$user['id']]);
    $wait_hod = (int)$stmt->fetchColumn();

    // Wait for Principal
    $stmt = $conn->prepare("SELECT COUNT(*) FROM leave_requests WHERE user_id = ? AND hod_status = 'Approved' AND principal_status = 'Pending'");
    $stmt->execute([$user['id']]);
    $wait_prin = (int)$stmt->fetchColumn();

    $response['pending_breakdown'] = [
        'waiting_substitute' => $wait_sub,
        'waiting_hod' => $wait_hod,
        'waiting_principal' => $wait_prin
    ];

    // 8. Substitution Status (Detailed)
    $stmt = $conn->prepare("
        SELECT s.status, COUNT(*) as count
        FROM leave_substitutions s
        JOIN leave_requests l ON s.leave_request_id = l.id
        WHERE l.user_id = ? AND MONTH(l.start_date) = ? AND YEAR(l.start_date) = ?
        GROUP BY s.status
    ");
    $stmt->execute([$user['id'], $month, $year]);
    $sub_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $response['substitution_stats'] = ['total' => 0, 'accepted' => 0, 'pending' => 0, 'rejected' => 0];
    foreach($sub_rows as $s) {
        $response['substitution_stats']['total'] += $s['count'];
        if($s['status'] === 'ACCEPTED') $response['substitution_stats']['accepted'] = $s['count'];
        if($s['status'] === 'PENDING') $response['substitution_stats']['pending'] = $s['count'];
        if($s['status'] === 'REJECTED') $response['substitution_stats']['rejected'] = $s['count'];
    }

    // 8.5 Substitution Requests Detail
    $stmt = $conn->prepare("
        SELECT u.name as substitute_faculty, s.status, s.date as leave_date
        FROM leave_substitutions s
        JOIN users u ON s.substitute_user_id = u.id
        JOIN leave_requests l ON s.leave_request_id = l.id
        WHERE l.user_id = ? AND s.status = 'PENDING'
    ");
    $stmt->execute([$user['id']]);
    $response['substitution_requests'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 9. Recent Activity (Latest 5 from leaves, permissions, outpasses union)
    $stmt = $conn->prepare("
        SELECT 'Leave Applied' as activity_type, leave_type, start_date, 
               CASE WHEN hod_status='Rejected' OR principal_status='Rejected' THEN 'Rejected'
                    WHEN hod_status='Cancelled' OR principal_status='Cancelled' THEN 'Cancelled'
                    WHEN principal_status='Approved' THEN 'Approved' ELSE 'Pending' END as status,
               created_at as sort_date
        FROM leave_requests WHERE user_id = ? AND hod_status != 'Cancelled'
        UNION ALL
        SELECT 'Permission Applied', 'Permission' as leave_type, permission_date as start_date, status, created_at as sort_date FROM faculty_permissions WHERE user_id = ?
        UNION ALL
        SELECT 'Outpass Applied', 'Outpass' as leave_type, outpass_date as start_date, status, created_at as sort_date FROM faculty_outpasses WHERE user_id = ?
        ORDER BY sort_date DESC LIMIT 5
    ");
    $stmt->execute([$user['id'], $user['id'], $user['id']]);
    $response['recent_leaves'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 10. Monthly Trends (Last 6 Months)
    $stmtTrend = $conn->prepare("
        SELECT DATE_FORMAT(start_date, '%Y-%m') as month, COUNT(*) as count 
        FROM leave_requests 
        WHERE user_id = ? AND start_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY month ORDER BY month ASC
    ");
    $stmtTrend->execute([$user['id']]);
    $stmtTrend->execute([$user['id']]);
    $response['monthly_trends'] = $stmtTrend->fetchAll(PDO::FETCH_ASSOC);

    // 11. Full Calendar Data (Faculty)
    $stmt = $conn->prepare("
        SELECT 'Leave' as type, leave_type as details, start_date as start, end_date as end, 
               CASE WHEN principal_status = 'Approved' THEN 'approved' 
                    WHEN hod_status = 'Rejected' OR principal_status = 'Rejected' THEN 'rejected' 
                    ELSE 'pending' END as status
        FROM leave_requests WHERE user_id = ?
        UNION ALL
        SELECT 'Permission', 'Permission', permission_date, permission_date, status FROM faculty_permissions WHERE user_id = ?
        UNION ALL
        SELECT 'Outpass', 'Outpass', outpass_date, outpass_date, status FROM faculty_outpasses WHERE user_id = ?
    ");
    $stmt->execute([$user['id'], $user['id'], $user['id']]);
    $response['full_leave_calendar'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 12. Leave Balance Summary
    $response['leave_balance'] = [
        'casual_leave' => [
            'total' => $cl_limit,
            'used' => $cl_used,
            'remaining' => max(0, $cl_limit - $cl_used)
        ],
        'permissions' => [
            'limit' => get_rule_limit($conn, 'Permission Limit', 1),
            'used' => $response['permissions_used']
        ],
        'outpasses' => [
            'limit' => get_rule_limit($conn, 'Outpass Limit', 1),
            'used' => $response['outpasses_used']
        ]
    ];

} elseif (strtolower($user['role']) === 'hod') {
    $dept = $user['department'];

    // 1. Total Faculty in Dept (including all teaching roles)
    $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE department = ? AND LOWER(role) IN ('faculty', 'assistant professor (ap)', 'associate professor', 'professor')");
    $stmt->execute([$dept]);
    $response['total_faculty'] = (int)$stmt->fetchColumn();

    // 2. Leaves Today in Dept
    $today = date('Y-m-d');
    $stmt = $conn->prepare("SELECT COUNT(*) FROM leave_requests l JOIN users u ON l.user_id = u.id WHERE u.department = ? AND ? BETWEEN l.start_date AND l.end_date AND l.hod_status = 'Approved' AND l.principal_status = 'Approved'");
    $stmt->execute([$dept, $today]);
    $response['leaves_today'] = (int)$stmt->fetchColumn();

    // 3. Permissions Used in Dept (this month)
    $stmt = $conn->prepare("SELECT COUNT(*) FROM faculty_permissions p JOIN users u ON p.user_id = u.id WHERE u.department = ? AND MONTH(p.permission_date) = ? AND YEAR(p.permission_date) = ? AND p.status != 'Rejected'");
    $stmt->execute([$dept, $month, $year]);
    $response['permissions_used'] = (int)$stmt->fetchColumn();

    // 4. Outpasses Used in Dept (this month)
    $stmt = $conn->prepare("SELECT COUNT(*) FROM faculty_outpasses o JOIN users u ON o.user_id = u.id WHERE u.department = ? AND MONTH(o.outpass_date) = ? AND YEAR(o.outpass_date) = ? AND o.status != 'Rejected'");
    $stmt->execute([$dept, $month, $year]);
    $response['outpasses_used'] = (int)$stmt->fetchColumn();
    
    // 5. Pending Approvals in Dept (HoD Approval Required)
    $stmt = $conn->prepare("SELECT COUNT(*) FROM leave_requests l JOIN users u ON l.user_id = u.id WHERE u.department = ? AND l.hod_status = 'Pending'");
    $stmt->execute([$dept]);
    $response['pending_approvals'] = (int)$stmt->fetchColumn();

    // 6. Pending Department Requests (List for HoD Action)
    $stmt = $conn->prepare("
        SELECT u.name as faculty_name, l.leave_type, l.start_date as leave_date, l.hod_status as status
        FROM leave_requests l 
        JOIN users u ON l.user_id = u.id 
        WHERE u.department = ? AND l.hod_status = 'Pending'
        ORDER BY l.created_at DESC
    ");
    $stmt->execute([$dept]);
    $response['pending_requests'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 7. Leave Type Distribution in Dept
    $stmt = $conn->prepare("SELECT leave_type, COUNT(*) as count FROM leave_requests l JOIN users u ON l.user_id = u.id WHERE u.department = ? AND MONTH(l.start_date) = ? AND YEAR(l.start_date) = ? AND l.hod_status != 'Rejected' AND l.principal_status != 'Rejected' GROUP BY leave_type");
    $stmt->execute([$dept, $month, $year]);
    $response['department_distribution'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 8. Faculty Leave Activity (Top 5 leave takers in Dept)
    $stmt = $conn->prepare("
        SELECT u.name, 
        (
            (SELECT COUNT(*) FROM leave_requests l WHERE l.user_id = u.id AND MONTH(l.start_date) = ? AND YEAR(l.start_date) = ?) +
            (SELECT COUNT(*) FROM faculty_permissions p WHERE p.user_id = u.id AND MONTH(p.permission_date) = ? AND YEAR(p.permission_date) = ?) +
            (SELECT COUNT(*) FROM faculty_outpasses o WHERE o.user_id = u.id AND MONTH(o.outpass_date) = ? AND YEAR(o.outpass_date) = ?)
        ) as leave_count
        FROM users u
        WHERE u.department = ? AND LOWER(u.role) IN ('faculty', 'assistant professor (ap)', 'associate professor', 'professor')
        HAVING leave_count > 0
        ORDER BY leave_count DESC
        LIMIT 5
    ");
    $stmt->execute([$month, $year, $month, $year, $month, $year, $dept]);
    $response['faculty_activity'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 9. Today's Leaves
    $stmt = $conn->prepare("
        SELECT u.name as faculty_name, l.leave_type
        FROM leave_requests l 
        JOIN users u ON l.user_id = u.id 
        WHERE u.department = ? AND ? BETWEEN l.start_date AND l.end_date 
        AND l.hod_status = 'Approved' AND l.principal_status = 'Approved'
    ");
    $stmt->execute([$dept, $today]);
    $response['todays_leave_list'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 10. Substitution Status Monitor
    $stmt = $conn->prepare("
        SELECT u.name as faculty_name, l.leave_type, s.status
        FROM leave_requests l
        JOIN users u ON l.user_id = u.id
        JOIN leave_substitutions s ON s.leave_request_id = l.id
        WHERE u.department = ? AND MONTH(l.start_date) = ? AND YEAR(l.start_date) = ?
        ORDER BY l.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$dept, $month, $year]);
    $response['substitution_status'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 11. Department Coverage (HOD View)
    $stmt = $conn->prepare("
        SELECT u.name, l.start_date, l.end_date, l.leave_type, l.hod_status 
        FROM leave_requests l 
        JOIN users u ON l.user_id = u.id 
        WHERE u.department = ? AND l.hod_status != 'Rejected'
    ");
    $stmt->execute([$dept]);
    $response['department_coverage'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 12. Leave Balance Summary (Dept)
    $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE department = ?");
    $stmt->execute([$dept]);
    $total_users = (int)$stmt->fetchColumn();
    $response['dept_balance_summary'] = [
        'total_faculty' => $total_users,
        'on_leave_today' => $response['leaves_today'],
        'pending_actions' => $response['pending_approvals']
    ];

} elseif (in_array(strtolower($user['role']), ['principal', 'admin'])) {
    // Check for drill-down request
    if (isset($_GET['department']) && !empty($_GET['department'])) {
        $drill_dept = $_GET['department'];
        
        // Fetch detailed faculty leave records for this department this month
        $stmt = $conn->prepare("
            SELECT u.name as faculty_name, l.leave_type, l.start_date as leave_date, 
                   CASE 
                       WHEN l.principal_status = 'Approved' THEN 'Approved'
                       WHEN l.hod_status = 'Rejected' OR l.principal_status = 'Rejected' THEN 'Rejected'
                       ELSE 'Pending'
                   END as status
            FROM leave_requests l
            JOIN users u ON l.user_id = u.id
            WHERE u.department = ? AND MONTH(l.start_date) = ? AND YEAR(l.start_date) = ?
            ORDER BY l.start_date DESC
        ");
        $stmt->execute([$drill_dept, $month, $year]);
        $response['department_drilldown'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $response['drilldown_stats'] = [
            'total' => count($response['department_drilldown']),
            'pending' => 0,
            'approved' => 0,
            'rejected' => 0
        ];
        
        foreach ($response['department_drilldown'] as $req) {
            if ($req['status'] === 'Approved') $response['drilldown_stats']['approved']++;
            elseif ($req['status'] === 'Rejected') $response['drilldown_stats']['rejected']++;
            else $response['drilldown_stats']['pending']++;
        }
        
        echo json_encode($response);
        exit();
    }

    // 1. Overall Leaves Today
    $today = date('Y-m-d');
    $stmt = $conn->prepare("SELECT COUNT(*) FROM leave_requests WHERE ? BETWEEN start_date AND end_date AND hod_status = 'Approved' AND principal_status = 'Approved'");
    $stmt->execute([$today]);
    $response['leaves_today'] = (int)$stmt->fetchColumn();

    // Active Faculty Today
    $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE role = 'faculty'");
    $stmt->execute();
    $total_faculty = (int)$stmt->fetchColumn();
    $response['active_faculty_today'] = max(0, $total_faculty - $response['leaves_today']);

    // 2. Pending Approvals Breakdown
    // Substitution Pending
    $stmt = $conn->prepare("SELECT COUNT(*) FROM leave_substitutions WHERE status = 'PENDING'");
    $stmt->execute();
    $sub_pending = (int)$stmt->fetchColumn();
    
    // HoD Pending
    $stmt = $conn->prepare("SELECT COUNT(*) FROM leave_requests WHERE hod_status = 'Pending'");
    $stmt->execute();
    $hod_pending = (int)$stmt->fetchColumn();
    
    // Principal Pending
    $stmt = $conn->prepare("SELECT COUNT(*) FROM leave_requests WHERE hod_status = 'Approved' AND principal_status = 'Pending'");
    $stmt->execute();
    $prin_pending = (int)$stmt->fetchColumn();
    
    $response['pending_approvals'] = $sub_pending + $hod_pending + $prin_pending;
    $response['pending_breakdown'] = [
        'substitution' => $sub_pending,
        'hod' => $hod_pending,
        'principal' => $prin_pending
    ];

    // 3. Overall Permissions
    $stmt = $conn->prepare("SELECT COUNT(*) FROM faculty_permissions WHERE MONTH(permission_date) = ? AND YEAR(permission_date) = ? AND status != 'Rejected'");
    $stmt->execute([$month, $year]);
    $response['permissions_used'] = (int)$stmt->fetchColumn();

    // 4. Overall Outpasses
    $stmt = $conn->prepare("SELECT COUNT(*) FROM faculty_outpasses WHERE MONTH(outpass_date) = ? AND YEAR(outpass_date) = ? AND status != 'Rejected'");
    $stmt->execute([$month, $year]);
    $response['outpasses_used'] = (int)$stmt->fetchColumn();

    // 5. Dept Breakdown (Month) - Total Leaves (Pending + Approved + Rejected)
    $stmt = $conn->prepare("SELECT u.department, COUNT(*) as count FROM leave_requests l JOIN users u ON l.user_id = u.id WHERE MONTH(l.start_date) = ? AND YEAR(l.start_date) = ? GROUP BY u.department");
    $stmt->execute([$month, $year]);
    $response['dept_stats'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Leave Type Breakdown (Month)
    $stmt = $conn->prepare("SELECT leave_type, COUNT(*) as count FROM leave_requests WHERE MONTH(start_date) = ? AND YEAR(start_date) = ? GROUP BY leave_type");
    $stmt->execute([$month, $year]);
    $response['leave_type_breakdown'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 6. High Frequency Users (Leaves + Permissions + Outpasses) -> Maps to faculty_activity
    $stmt = $conn->prepare("
        SELECT u.id, u.name, u.department, 
        (
            (SELECT COUNT(*) FROM leave_requests l WHERE l.user_id = u.id AND MONTH(l.start_date) = ? AND YEAR(l.start_date) = ?) +
            (SELECT COUNT(*) FROM faculty_permissions p WHERE p.user_id = u.id AND MONTH(p.permission_date) = ? AND YEAR(p.permission_date) = ?) +
            (SELECT COUNT(*) FROM faculty_outpasses o WHERE o.user_id = u.id AND MONTH(o.outpass_date) = ? AND YEAR(o.outpass_date) = ?)
        ) as leave_count
        FROM users u
        WHERE LOWER(u.role) IN ('faculty', 'assistant professor (ap)', 'associate professor', 'professor')
        HAVING leave_count > 0
        ORDER BY leave_count DESC
        LIMIT 10
    ");
    $stmt->execute([$month, $year, $month, $year, $month, $year]);
    $response['faculty_activity'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Today's Leave List -> Maps to todays_leave_list
    $stmt = $conn->prepare("
        SELECT u.name as faculty_name, u.department 
        FROM leave_requests l 
        JOIN users u ON l.user_id = u.id 
        WHERE ? BETWEEN l.start_date AND l.end_date 
        AND l.hod_status = 'Approved' AND l.principal_status = 'Approved'
    ");
    $stmt->execute([$today]);
    $response['todays_leave_list'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Substitution Status -> Maps to substitution_status
    $stmt = $conn->prepare("
        SELECT u.name as faculty_name, l.leave_type, s.status
        FROM leave_requests l
        JOIN users u ON l.user_id = u.id
        JOIN leave_substitutions s ON s.leave_request_id = l.id
        WHERE MONTH(l.start_date) = ? AND YEAR(l.start_date) = ?
        ORDER BY l.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$month, $year]);
    $response['substitution_status'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 7. System-wide Monthly Trends (Last 6 Months)
    $stmtTrend = $conn->prepare("
        SELECT DATE_FORMAT(start_date, '%Y-%m') as month, COUNT(*) as count 
        FROM leave_requests 
        WHERE start_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY month ORDER BY month ASC
    ");
    $stmtTrend->execute();
    $response['monthly_trends'] = $stmtTrend->fetchAll(PDO::FETCH_ASSOC);

    // 8. Global Coverage (Principal/Admin)
    $stmt = $conn->prepare("
        SELECT u.name, u.department, l.start_date, l.end_date, l.leave_type 
        FROM leave_requests l 
        JOIN users u ON l.user_id = u.id 
        WHERE l.principal_status = 'Approved'
    ");
    $stmt->execute();
    $response['global_coverage'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response['total_leaves'] = $response['leaves_today'];
}

$jsonResponse = json_encode($response);
file_put_contents($cacheFile, $jsonResponse);
echo $jsonResponse;
?>
