<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../core/auth_guard.php';

use App\Repositories\NotificationRepository;

$method = $_SERVER['REQUEST_METHOD'];
$path = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '/';
$notifRepo = new NotificationRepository();

try {
    if ($method === 'GET') {
        if ($path === '/unread-count') {
            $unread = $notifRepo->getUnreadForUser($global_user['id']);
            echo json_encode(["unread_count" => count($unread)]);
        } else {
            // Default to all notifications (unread + some read)
            // The repository currently only has getUnreadForUser, 
            // let's stick to that for the refactor or expand it.
            echo json_encode($notifRepo->getUnreadForUser($global_user['id']));
        }
    } elseif ($method === 'PUT') {
        if (preg_match('/^\/(\d+)\/read$/', $path, $matches)) {
            $notifRepo->markAsRead($matches[1], $global_user['id']);
            echo json_encode(["message" => "Marked as read"]);
        } elseif ($path === '/read-all') {
            $notifRepo->markAllRead($global_user['id']);
            echo json_encode(["message" => "All notifications marked as read"]);
        }

    } else {
        http_response_code(404);
        echo json_encode(["error" => "Not found"]);
    }
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
