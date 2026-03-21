<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../core/bootstrap.php';
require_once __DIR__ . '/../core/auth_guard.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/audit.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = $conn->prepare("SELECT signature_path FROM users WHERE id = ?");
    $stmt->execute([$global_user['id']]);
    $row = $stmt->fetch();
    echo json_encode(["signature" => $row['signature_path'] ?? null]);
    exit();
}

if ($method !== 'POST') {

$user = $global_user;

// Role Check: Only HOD or Principal (or Admin) - using helper
if (!isAdministrativeRole($user['role'])) {
    http_response_code(403);
    echo json_encode(["error" => "Only HOD or Principal can upload signatures"]);
    exit();
}

if (!isset($_FILES['signature'])) {
    http_response_code(400);
    echo json_encode(["error" => "No file uploaded"]);
    exit();
}

$file = $_FILES['signature'];
$allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];

if (!in_array($file['type'], $allowedTypes)) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid file type. Only JPG/PNG allowed."]);
    exit();
}

if ($file['size'] > 2 * 1024 * 1024) { // 2MB
    http_response_code(400);
    echo json_encode(["error" => "File too large. Max 2MB."]);
    exit();
}

// Save File
$uploadDir = '../uploads/signatures/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Naming: signature_{user_id}.ext (Overwrite existing)
$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = 'signature_' . $user['id'] . '.' . $ext;
$targetPath = $uploadDir . $filename;

if (move_uploaded_file($file['tmp_name'], $targetPath)) {
    // Update Database
    try {
        // Prepare relative path for DB (or filename)
        // Let's store clear filename or relative path
        $dbPath = 'uploads/signatures/' . $filename;
        
        $stmt = $conn->prepare("UPDATE users SET signature_path = ? WHERE id = ?");
        $stmt->execute([$dbPath, $user['id']]);
        
        logAudit($conn, $user['id'], 'UPLOAD_SIGNATURE', ['file' => $filename]);
        
        echo json_encode(["message" => "Signature uploaded successfully", "path" => $dbPath]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Database error: " . $e->getMessage()]);
    }
} else {
    http_response_code(500);
    echo json_encode(["error" => "Failed to move uploaded file"]);
}
